<?php

namespace App\Services\Wbs;

use App\Enums\AksiAudit;
use App\Enums\KedudukanTerlapor;
use App\Enums\KesimpulanWbs;
use App\Enums\StatusLaporan;
use App\Enums\TahapWbs;
use App\Events\HasilWbsDikirim;
use App\Events\InvestigatorWbsDitunjuk;
use App\Events\LaporanWbsPerluDitelaah;
use App\Events\PerpanjanganWbsDiajukan;
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
 * yang ditandai tampil_ke_pelapor. Perpanjangan investigasi diajukan Tim WBS dan diputuskan penerima hasil di
 * Hasil WBS untuk Saya (H-16); kesimpulan dikirim ke penerima hasil tanpa identitas pelapor.
 */
class PenangananWbs
{
    public const MAKS_CATATAN = 2000;

    /** Awalan catatan riwayat pengajuan perpanjangan; alasannya ditampilkan kepada penerima hasil di H-16. */
    public const AWALAN_PERPANJANGAN = 'Perpanjangan investigasi diajukan kepada penerima hasil. Alasan:';

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
            'ajukanPerpanjangan' => $laporan->status === StatusLaporan::Diinvestigasi && $laporan->perpanjangan_diajukan_pada === null,
            'putuskanPerpanjangan' => $laporan->status === StatusLaporan::Diinvestigasi && $laporan->perpanjangan_diajukan_pada !== null,
            'tandaiDitindaklanjuti' => $laporan->kesimpulan !== null && $laporan->hasil_ditindaklanjuti_pada === null,
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
     * Hasil dikirim ke penerima hasil menurut matriks (H-16) tanpa identitas pelapor; pengajuan perpanjangan yang
     * belum diputuskan ikut gugur.
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
                $terkunci->perpanjangan_diajukan_pada = null;

                HasilWbsDikirim::dispatch($terkunci);

                return "Kesimpulan: {$kesimpulan->getLabel()}. Hasil dikirim ke penerima hasil.";
            },
            catatanPelapor: $catatanPelapor,
            beritahuPelapor: false,
        );
    }

    /**
     * Pengajuan perpanjangan 30 hari kerja (UC-13 1a) oleh penelaah utama atau investigator. Status belum berubah sampai
     * penerima hasil menyetujui.
     */
    public function ajukanPerpanjangan(Laporan $laporan, User $oleh, string $alasan): Laporan
    {
        Gate::forUser($oleh)->authorize('simpulkanLaporanWbs', $laporan);
        $alasan = $this->wajib($alasan, 'Alasan perpanjangan');

        return $this->catatTanpaUbahStatus($laporan, $oleh, 'ajukanPerpanjangan', 'diajukan perpanjangannya', AksiAudit::MengajukanPerpanjangan,
            function (Laporan $terkunci) use ($alasan): string {
                $terkunci->perpanjangan_diajukan_pada = now();
                PerpanjanganWbsDiajukan::dispatch($terkunci);

                return self::AWALAN_PERPANJANGAN.' '.$alasan;
            });
    }

    /**
     * Penerima hasil menyetujui perpanjangan: status Diinvestigasi (diperpanjang), batas bertambah 30 hari kerja dari
     * batas semula, dan persetujuan tercatat (laporan.perpanjangan_disetujui_oleh).
     */
    public function setujuiPerpanjangan(Laporan $laporan, User $penerima): Laporan
    {
        Gate::forUser($penerima)->authorize('putuskanHasilWbs', $laporan);

        return $this->pengubah->ubah(
            $laporan,
            $penerima,
            fn (Laporan $terkunci): ?AksiWbsTidakSah => self::bisa('putuskanPerpanjangan', $terkunci)
                ? null
                : AksiWbsTidakSah::statusWbsTidakSesuai('diputuskan perpanjangannya', $terkunci->status),
            StatusLaporan::DiinvestigasiDiperpanjang,
            AksiAudit::MenyetujuiPerpanjangan,
            function (Laporan $terkunci) use ($penerima): string {
                $terkunci->perpanjangan_disetujui_oleh = $penerima->getKey();
                $terkunci->perpanjangan_diajukan_pada = null;
                $terkunci->batas_waktu = TahapWbs::Perpanjangan->batasDari($terkunci->batas_waktu ?? now());

                return "Perpanjangan disetujui {$penerima->name}.";
            },
            beritahuPelapor: false,
        );
    }

    public function tolakPerpanjangan(Laporan $laporan, User $penerima, string $alasan): Laporan
    {
        Gate::forUser($penerima)->authorize('putuskanHasilWbs', $laporan);
        $alasan = $this->wajib($alasan, 'Alasan penolakan');

        return $this->catatTanpaUbahStatus($laporan, $penerima, 'putuskanPerpanjangan', 'diputuskan perpanjangannya', AksiAudit::MenolakPerpanjangan,
            function (Laporan $terkunci) use ($penerima, $alasan): string {
                $terkunci->perpanjangan_diajukan_pada = null;

                return "Perpanjangan ditolak {$penerima->name}. Alasan: {$alasan}";
            });
    }

    /**
     * Penerima hasil menandai hasil sudah ditindaklanjuti (H-16). Catatan tindak lanjut hanya untuk petugas.
     */
    public function tandaiDitindaklanjuti(Laporan $laporan, User $penerima, string $catatan): Laporan
    {
        Gate::forUser($penerima)->authorize('putuskanHasilWbs', $laporan);
        $catatan = $this->wajib($catatan, 'Catatan tindak lanjut');

        return $this->catatTanpaUbahStatus($laporan, $penerima, 'tandaiDitindaklanjuti', 'ditandai ditindaklanjuti', AksiAudit::MenandaiHasilDitindaklanjuti,
            function (Laporan $terkunci) use ($penerima, $catatan): string {
                $terkunci->hasil_ditindaklanjuti_pada = now();
                $terkunci->hasil_ditindaklanjuti_oleh = $penerima->getKey();

                return "Tindak lanjut hasil oleh {$penerima->name}: {$catatan}";
            });
    }

    /**
     * Aksi yang tidak mengubah status: dicatat di riwayat (status sama, catatan petugas) dan log audit.
     *
     * @param  callable(Laporan): string  $ubah
     */
    private function catatTanpaUbahStatus(Laporan $laporan, User $oleh, string $aksi, string $kataKerja, AksiAudit $audit, callable $ubah): Laporan
    {
        return DB::transaction(function () use ($laporan, $oleh, $aksi, $kataKerja, $audit, $ubah): Laporan {
            $terkunci = Laporan::query()->lockForUpdate()->findOrFail($laporan->getKey());

            if (! self::bisa($aksi, $terkunci)) {
                throw AksiWbsTidakSah::statusWbsTidakSesuai($kataKerja, $terkunci->status);
            }

            $catatan = $ubah($terkunci);
            $terkunci->save();

            $terkunci->riwayatStatus()->create([
                'status_lama' => $terkunci->status,
                'status_baru' => $terkunci->status,
                'oleh_user_id' => $oleh->getKey(),
                'catatan' => $catatan,
                'tampil_ke_pelapor' => false,
            ]);

            LogAudit::catat($oleh, $audit, $terkunci);

            return $terkunci;
        });
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
