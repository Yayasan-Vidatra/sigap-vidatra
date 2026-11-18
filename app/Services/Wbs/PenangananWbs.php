<?php

namespace App\Services\Wbs;

use App\Enums\AksiAudit;
use App\Enums\KedudukanTerlapor;
use App\Enums\KesimpulanWbs;
use App\Enums\StatusLaporan;
use App\Enums\TahapWbs;
use App\Events\InvestigatorWbsDitunjuk;
use App\Events\LaporanWbsPerluDitelaah;
use App\Exceptions\AksiWbsTidakSah;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\Pengaduan\PengubahStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Aksi Ruang Kerja WBS (H-13): telaah (UC-12, US-15), penunjukan investigator dan kesimpulan (UC-13, US-17).
 * Setiap aksi memeriksa ulang hak akses (LaporanPolicy) dan status terbaru di server, lalu mencatat riwayat_status
 * dan log audit. Catatan riwayat terenkripsi; hanya alasan umum "Tidak Ditindaklanjuti" dan catatan untuk pelapor
 * yang ditandai tampil_ke_pelapor. Perpanjangan investigasi dengan persetujuan penerima hasil menyusul bersama
 * Hasil WBS untuk Saya (H-16, Hari 11).
 */
class PenangananWbs
{
    public const MAKS_CATATAN = 2000;

    /**
     * Status tempat telaah masih berjalan (koreksi kedudukan dan keputusan kelayakan).
     *
     * @var list<StatusLaporan>
     */
    public const STATUS_TELAAH = [StatusLaporan::Diterima, StatusLaporan::Ditelaah];

    /**
     * @var list<StatusLaporan>
     */
    public const STATUS_INVESTIGASI = [StatusLaporan::Diinvestigasi, StatusLaporan::DiinvestigasiDiperpanjang];

    public function __construct(private PengubahStatus $pengubah) {}

    /**
     * Apakah aksi tersedia pada status laporan saat ini (untuk menampilkan tombol); hak akses diperiksa terpisah.
     */
    public static function bisa(string $aksi, Laporan $laporan): bool
    {
        return match ($aksi) {
            'mulaiTelaah' => $laporan->status === StatusLaporan::Diterima,
            'koreksiKedudukan', 'tidakDitindaklanjuti' => in_array($laporan->status, self::STATUS_TELAAH, true),
            'mulaiInvestigasi' => $laporan->status === StatusLaporan::Ditelaah,
            'simpulkan' => in_array($laporan->status, self::STATUS_INVESTIGASI, true),
            default => false,
        };
    }

    public function mulaiTelaah(Laporan $laporan, User $oleh): Laporan
    {
        Gate::forUser($oleh)->authorize('telaahLaporanWbs', $laporan);

        return $this->pengubah->ubah(
            $laporan,
            $oleh,
            self::dariStatus([StatusLaporan::Diterima], 'mulai ditelaah'),
            StatusLaporan::Ditelaah,
            AksiAudit::MemulaiTelaahWbs,
            beritahuPelapor: false,
        );
    }

    /**
     * Koreksi kedudukan terlapor pada telaah awal (Bagian 3.1, US-15). Status tidak berubah; koreksi dicatat di
     * riwayat sebagai catatan petugas. Bila koreksi memindahkan laporan ke penelaah lain, akses Ketua berpindah
     * dengan sendirinya (AksesWbs) dan penelaah baru diberi tahu.
     */
    public function koreksiKedudukan(Laporan $laporan, User $oleh, KedudukanTerlapor $baru, string $alasan): Laporan
    {
        Gate::forUser($oleh)->authorize('telaahLaporanWbs', $laporan);
        $alasan = $this->wajib($alasan, 'Alasan koreksi');

        return DB::transaction(function () use ($laporan, $oleh, $baru, $alasan): Laporan {
            $terkunci = Laporan::query()->lockForUpdate()->findOrFail($laporan->getKey());

            if (! self::bisa('koreksiKedudukan', $terkunci)) {
                throw AksiWbsTidakSah::statusWbsTidakSesuai('dikoreksi kedudukan terlapornya', $terkunci->status);
            }

            $lama = $terkunci->kedudukan_terlapor ?? KedudukanTerlapor::TidakTahu;

            if ($lama === $baru) {
                throw AksiWbsTidakSah::kedudukanSama();
            }

            $penelaahLama = AksesWbs::kedudukanEfektif($terkunci)->peranPenelaahUtama();
            $terkunci->kedudukan_terlapor = $baru;
            $terkunci->save();

            $terkunci->riwayatStatus()->create([
                'status_lama' => $terkunci->status,
                'status_baru' => $terkunci->status,
                'oleh_user_id' => $oleh->getKey(),
                'catatan' => "Kedudukan terlapor dikoreksi dari \"{$lama->getLabel()}\" menjadi \"{$baru->getLabel()}\". Alasan: {$alasan}",
                'tampil_ke_pelapor' => false,
            ]);

            LogAudit::catat($oleh, AksiAudit::MengoreksiKedudukanTerlapor, $terkunci);

            if (AksesWbs::kedudukanEfektif($terkunci->fresh())->peranPenelaahUtama() !== $penelaahLama) {
                LaporanWbsPerluDitelaah::dispatch($terkunci, true);
            }

            return $terkunci;
        });
    }

    /**
     * Tidak layak investigasi (Bagian 6): alasan umum wajib dan tampil ke pelapor.
     */
    public function tidakDitindaklanjuti(Laporan $laporan, User $oleh, string $alasanUmum): Laporan
    {
        Gate::forUser($oleh)->authorize('telaahLaporanWbs', $laporan);
        $alasanUmum = $this->wajib($alasanUmum, 'Alasan umum');

        return $this->pengubah->ubah(
            $laporan,
            $oleh,
            self::dariStatus(self::STATUS_TELAAH, 'dinyatakan tidak ditindaklanjuti'),
            StatusLaporan::TidakDitindaklanjuti,
            AksiAudit::MenghentikanLaporanWbs,
            function (Laporan $terkunci): ?string {
                $terkunci->batas_waktu = null;

                return null;
            },
            catatanPelapor: $alasanUmum,
        );
    }

    /**
     * Layak investigasi: investigator ditunjuk dan batas investigasi 30 hari kerja dihitung (UC-13 langkah 1).
     */
    public function mulaiInvestigasi(Laporan $laporan, User $oleh, User $investigator, string $rencana): Laporan
    {
        Gate::forUser($oleh)->authorize('telaahLaporanWbs', $laporan);
        $rencana = $this->wajib($rencana, 'Hasil telaah dan rencana investigasi');

        if (! AksesWbs::calonInvestigator($laporan)->contains(fn (User $calon): bool => $calon->is($investigator))) {
            throw AksiWbsTidakSah::investigatorTidakSah();
        }

        return DB::transaction(function () use ($laporan, $oleh, $investigator, $rencana): Laporan {
            $hasil = $this->pengubah->ubah(
                $laporan,
                $oleh,
                self::dariStatus([StatusLaporan::Ditelaah], 'mulai diinvestigasi'),
                StatusLaporan::Diinvestigasi,
                AksiAudit::MenunjukInvestigator,
                function (Laporan $terkunci) use ($investigator, $rencana): string {
                    $terkunci->investigator_user_id = $investigator->getKey();
                    $terkunci->batas_waktu = TahapWbs::Investigasi->batasDari(now());

                    return "Layak investigasi. Investigator: {$investigator->name}. {$rencana}";
                },
                beritahuPelapor: false,
            );

            InvestigatorWbsDitunjuk::dispatch($hasil, $investigator);

            return $hasil;
        });
    }

    /**
     * Kesimpulan dan rekomendasi (UC-13 langkah 5). Tim WBS tidak menjatuhkan sanksi; rekomendasi terenkripsi.
     * Pengiriman ke penerima hasil menurut matriks menyusul bersama H-16 (Hari 11).
     */
    public function simpulkan(Laporan $laporan, User $oleh, KesimpulanWbs $kesimpulan, string $rekomendasi, ?string $catatanPelapor): Laporan
    {
        Gate::forUser($oleh)->authorize('simpulkanLaporanWbs', $laporan);
        $rekomendasi = $this->wajib($rekomendasi, 'Rekomendasi');
        $catatanPelapor = filled($catatanPelapor) ? mb_substr(trim((string) $catatanPelapor), 0, self::MAKS_CATATAN) : null;

        return $this->pengubah->ubah(
            $laporan,
            $oleh,
            self::dariStatus(self::STATUS_INVESTIGASI, 'disimpulkan'),
            StatusLaporan::Selesai,
            AksiAudit::MenetapkanKesimpulanWbs,
            function (Laporan $terkunci) use ($kesimpulan, $rekomendasi): string {
                $terkunci->kesimpulan = $kesimpulan;
                $terkunci->rekomendasi = $rekomendasi;
                $terkunci->batas_waktu = null;

                return "Kesimpulan: {$kesimpulan->getLabel()}.";
            },
            catatanPelapor: $catatanPelapor,
            beritahuPelapor: false,
        );
    }

    /**
     * @param  list<StatusLaporan>  $statusAsal
     * @return callable(Laporan): ?AksiWbsTidakSah
     */
    private static function dariStatus(array $statusAsal, string $kataKerja): callable
    {
        return fn (Laporan $laporan): ?AksiWbsTidakSah => in_array($laporan->status, $statusAsal, true)
            ? null
            : AksiWbsTidakSah::statusWbsTidakSesuai($kataKerja, $laporan->status);
    }

    private function wajib(string $teks, string $isian): string
    {
        $teks = trim($teks);

        if ($teks === '') {
            throw AksiWbsTidakSah::isianWajib($isian);
        }

        return mb_substr($teks, 0, self::MAKS_CATATAN);
    }
}
