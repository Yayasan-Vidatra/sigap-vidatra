<?php

namespace App\Services\Pengalihan;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\TujuanPengalihan;
use App\Events\PengalihanDikirim;
use App\Exceptions\AksiPengalihanTidakSah;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\Pengalihan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaduan\PengubahStatus;
use App\Services\Wbs\AksesWbs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Pengalihan laporan kekerasan atau etik (UC-19, US-19, US-20, F-18): Admin Pengaduan dari H-11 atau penelaah utama
 * WBS dari H-13 mengirim ringkasan tanpa identitas pelapor (terenkripsi) kepada Kepala Sekolah unit tujuan atau Komite
 * Etik, yang lalu mengonfirmasi terima dan mencatat tindak lanjut di H-17. Pengalihan ke Kepala Sekolah ditolak bila
 * Kepala Sekolah unit itu termasuk yang dilaporkan; Tim WBS lalu merujuk ke Pokja BSAN (di luar sistem) dengan
 * pemberitahuan kepada Ketua Umum dan Dewan Pengawas.
 */
class PengalihanLaporan
{
    public const MAKS_RINGKASAN = 3000;

    public function __construct(private PengubahStatus $pengubah) {}

    /**
     * Status yang masih boleh dialihkan; status akhir (Selesai, Ditolak, Tidak Ditindaklanjuti, Dipindah ke WBS, dan
     * semua status Dialihkan) tidak.
     *
     * @return list<StatusLaporan>
     */
    public static function statusBolehDialihkan(JenisLaporan $jenis): array
    {
        return $jenis === JenisLaporan::Wbs
            ? [StatusLaporan::Diterima, StatusLaporan::Ditelaah, StatusLaporan::Diinvestigasi, StatusLaporan::DiinvestigasiDiperpanjang]
            : [StatusLaporan::Diterima, StatusLaporan::Terverifikasi, StatusLaporan::Diproses];
    }

    public static function bisa(Laporan $laporan): bool
    {
        return in_array($laporan->status, self::statusBolehDialihkan($laporan->jenis), true);
    }

    /**
     * @return list<TujuanPengalihan>
     */
    public static function tujuanUntuk(Laporan $laporan): array
    {
        return $laporan->isWbs()
            ? [TujuanPengalihan::KepalaSekolah, TujuanPengalihan::KomiteEtik, TujuanPengalihan::PokjaBsan]
            : [TujuanPengalihan::KepalaSekolah, TujuanPengalihan::KomiteEtik];
    }

    public function alihkan(Laporan $laporan, User $oleh, TujuanPengalihan $tujuan, ?Unit $unitTujuan, string $ringkasan, bool $kepalaSekolahTerlibat): Pengalihan
    {
        Gate::forUser($oleh)->authorize($laporan->isWbs() ? 'telaahLaporanWbs' : 'kelolaPengaduan', $laporan);

        $ringkasan = trim($ringkasan);

        if ($ringkasan === '') {
            throw AksiPengalihanTidakSah::ringkasanWajib();
        }

        if (! in_array($tujuan, self::tujuanUntuk($laporan), true)) {
            throw AksiPengalihanTidakSah::tujuanTidakBerlaku();
        }

        $unitTujuan = $tujuan === TujuanPengalihan::KepalaSekolah ? ($unitTujuan ?? $laporan->unit) : null;
        $kepalaSekolahTerlibat = $kepalaSekolahTerlibat || ($unitTujuan !== null && self::kepalaSekolahDisebut($laporan, $unitTujuan));

        if ($tujuan === TujuanPengalihan::KepalaSekolah && $kepalaSekolahTerlibat) {
            throw AksiPengalihanTidakSah::kepalaSekolahTerlibat($laporan->isWbs());
        }

        if ($tujuan !== TujuanPengalihan::PokjaBsan && self::penerima($tujuan, $unitTujuan, $laporan)->isEmpty()) {
            throw AksiPengalihanTidakSah::tidakAdaPenerima();
        }

        $statusBaru = match ($tujuan) {
            TujuanPengalihan::KepalaSekolah => StatusLaporan::DialihkanKeSekolah,
            TujuanPengalihan::KomiteEtik => StatusLaporan::DialihkanKeKomiteEtik,
            TujuanPengalihan::PokjaBsan => StatusLaporan::DirujukKePokjaBsan,
        };

        return DB::transaction(function () use ($laporan, $oleh, $tujuan, $unitTujuan, $ringkasan, $kepalaSekolahTerlibat, $statusBaru): Pengalihan {
            $pengalihan = null;

            $this->pengubah->ubah(
                $laporan,
                $oleh,
                fn (Laporan $terkunci): ?AksiPengalihanTidakSah => self::bisa($terkunci)
                    ? null
                    : new AksiPengalihanTidakSah("Laporan berstatus \"{$terkunci->status->getLabel()}\" tidak dapat dialihkan. Muat ulang halaman."),
                $statusBaru,
                AksiAudit::MengalihkanLaporan,
                function (Laporan $terkunci) use ($oleh, $tujuan, $unitTujuan, $ringkasan, $kepalaSekolahTerlibat, &$pengalihan): string {
                    $terkunci->dialihkan_ke = $tujuan;
                    $terkunci->batas_waktu = null;
                    $pengalihan = $terkunci->pengalihan()->create([
                        'tujuan' => $tujuan,
                        'unit_tujuan_id' => $unitTujuan?->getKey(),
                        'kepala_sekolah_terlapor' => $kepalaSekolahTerlibat,
                        'ringkasan' => mb_substr($ringkasan, 0, self::MAKS_RINGKASAN),
                        'dikirim_oleh' => $oleh->getKey(),
                    ]);

                    return 'Dialihkan ke '.$tujuan->getLabel().($unitTujuan ? " {$unitTujuan->nama}" : '').'.';
                },
            );

            PengalihanDikirim::dispatch($pengalihan);

            return $pengalihan;
        });
    }

    public function konfirmasiTerima(Pengalihan $pengalihan, User $penerima): Pengalihan
    {
        Gate::forUser($penerima)->authorize('view', $pengalihan);

        return DB::transaction(function () use ($pengalihan, $penerima): Pengalihan {
            $terkunci = Pengalihan::query()->lockForUpdate()->findOrFail($pengalihan->getKey());

            if ($terkunci->diterima_pada !== null) {
                throw AksiPengalihanTidakSah::sudahDikonfirmasi();
            }

            $terkunci->forceFill(['diterima_pada' => now(), 'diterima_oleh' => $penerima->getKey()])->save();
            LogAudit::catat($penerima, AksiAudit::MengonfirmasiPengalihan, $terkunci);

            return $terkunci;
        });
    }

    public function catatTindakLanjut(Pengalihan $pengalihan, User $penerima, string $catatan): Pengalihan
    {
        Gate::forUser($penerima)->authorize('view', $pengalihan);
        $catatan = trim($catatan);

        if ($catatan === '') {
            throw new AksiPengalihanTidakSah('Catatan tindak lanjut wajib diisi.');
        }

        if ($pengalihan->fresh()?->diterima_pada === null) {
            throw AksiPengalihanTidakSah::belumDikonfirmasi();
        }

        $pengalihan->forceFill(['catatan_tindak_lanjut' => mb_substr($catatan, 0, self::MAKS_RINGKASAN)])->save();
        LogAudit::catat($penerima, AksiAudit::MencatatTindakLanjutPengalihan, $pengalihan);

        return $pengalihan;
    }

    /**
     * Penerima pengalihan yang aktif: Kepala Sekolah unit tujuan atau Komite Etik; untuk rujukan Pokja BSAN, Ketua Umum
     * dan Dewan Pengawas yang diberi tahu. Pejabat yang disebut sebagai terlapor WBS tidak termasuk.
     *
     * @return Collection<int, User>
     */
    public static function penerima(TujuanPengalihan $tujuan, ?Unit $unitTujuan, Laporan $laporan): Collection
    {
        $query = User::query()->whereNull('terkunci_pada');

        match ($tujuan) {
            TujuanPengalihan::KepalaSekolah => $query->where('peran', Peran::KepalaSekolah->value)->where('unit_id', $unitTujuan?->getKey()),
            TujuanPengalihan::KomiteEtik => $query->where('peran', Peran::KomiteEtik->value),
            TujuanPengalihan::PokjaBsan => $query->whereIn('peran', [Peran::KetuaUmum->value, Peran::DewanPengawas->value]),
        };

        return $query->get()->reject(fn (User $user): bool => $laporan->isWbs() && AksesWbs::disebutSebagaiTerlapor($user, $laporan))->values();
    }

    /**
     * Lapis kedua US-20 untuk WBS: nama Kepala Sekolah unit tujuan disebut sebagai terlapor.
     */
    private static function kepalaSekolahDisebut(Laporan $laporan, Unit $unit): bool
    {
        if (! $laporan->isWbs()) {
            return false;
        }

        return User::query()
            ->where('peran', Peran::KepalaSekolah->value)
            ->where('unit_id', $unit->getKey())
            ->get()
            ->contains(fn (User $kepsek): bool => AksesWbs::disebutSebagaiTerlapor($kepsek, $laporan));
    }
}
