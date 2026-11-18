<?php

namespace App\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Enums\Peran;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Events\PengaduanDidisposisikan;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Disposisi;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Alur Admin Pengaduan di Kotak Masuk (H-11) menurut Rancangan r1 Bagian 6:
 * - verifikasi dan kategorisasi (UC-08): Diterima → Terverifikasi;
 * - tolak dengan alasan wajib (UC-11): Diterima atau Terverifikasi → Ditolak, selama belum ada disposisi;
 *   alasannya boleh dibaca pelapor (riwayat_status.tampil_ke_pelapor);
 * - disposisi ke unit, PIC, prioritas, dan instruksi (UC-09): Terverifikasi → Diproses, dengan batas
 *   waktu dari SLA kategori dalam hari kerja. Bila unit berganti, unit lama dan baru dicatat di
 *   riwayat_status (hanya untuk petugas) dan log audit.
 *
 * - tutup karena pelapor mengonfirmasi lewat telepon: Menunggu Konfirmasi → Selesai, dengan catatan wajib
 *   yang hanya untuk petugas. Ini satu-satunya penutupan oleh Admin; selebihnya UC-11 dipenuhi lewat Ditolak,
 *   dan Selesai diubah pelapor atau sistem (Bagian 6; klarifikasi di prd/log-deviasi.md).
 *
 * Hak akses diperiksa di sini, bukan hanya di tombol. Perubahan status dijalankan PengubahStatus, yang
 * mengunci baris laporan, memeriksa status terbaru, mencatat riwayat_status dan log audit, lalu mengirim
 * event setelah transaksi tersimpan.
 */
class PenangananPengaduan
{
    public function __construct(private PengubahStatus $pengubah) {}

    /**
     * Status asal yang sah untuk setiap aksi.
     *
     * @var array<string, list<StatusLaporan>>
     */
    public const STATUS_ASAL = [
        'verifikasi' => [StatusLaporan::Diterima],
        'tolak' => [StatusLaporan::Diterima, StatusLaporan::Terverifikasi],
        'disposisi' => [StatusLaporan::Terverifikasi],
        'tutup' => [StatusLaporan::MenungguKonfirmasi],
    ];

    public static function bisa(string $aksi, Laporan $laporan): bool
    {
        return self::alasanTidakBisa($aksi, $laporan) === null;
    }

    private static function alasanTidakBisa(string $aksi, Laporan $laporan): ?AksiPengaduanTidakSah
    {
        if (! in_array($laporan->status, self::STATUS_ASAL[$aksi], true)) {
            return AksiPengaduanTidakSah::statusTidakSesuai(self::KATA_KERJA[$aksi], $laporan->status);
        }

        if ($aksi === 'tolak' && $laporan->disposisi()->exists()) {
            return AksiPengaduanTidakSah::sudahDidisposisikan();
        }

        return null;
    }

    /**
     * @var array<string, string>
     */
    private const KATA_KERJA = [
        'verifikasi' => 'diverifikasi',
        'tolak' => 'ditolak',
        'disposisi' => 'didisposisikan',
        'tutup' => 'ditutup',
    ];

    public function verifikasi(Laporan $laporan, User $oleh, Kategori $kategori): Laporan
    {
        if ($kategori->jenis !== JenisLaporan::Pengaduan) {
            throw AksiPengaduanTidakSah::kategoriBukanPengaduan();
        }

        return $this->ubahStatus($laporan, $oleh, 'verifikasi', StatusLaporan::Terverifikasi,
            AksiAudit::MemverifikasiPengaduan, function (Laporan $terkunci) use ($kategori): ?string {
                $terkunci->kategori()->associate($kategori);

                return null;
            });
    }

    public function tolak(Laporan $laporan, User $oleh, string $alasan): Laporan
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw AksiPengaduanTidakSah::alasanWajib();
        }

        return $this->ubahStatus($laporan, $oleh, 'tolak', StatusLaporan::Ditolak,
            AksiAudit::MenolakPengaduan, catatanPelapor: $alasan);
    }

    /**
     * Admin menutup pengaduan karena pelapor sudah mengonfirmasi hasilnya lewat telepon. Catatannya wajib
     * dan tidak terlihat pelapor (tampil_ke_pelapor false).
     */
    public function tutupLewatTelepon(Laporan $laporan, User $oleh, string $catatan): Laporan
    {
        $catatan = trim($catatan);

        if ($catatan === '') {
            throw AksiPengaduanTidakSah::catatanPenutupanWajib();
        }

        return $this->ubahStatus($laporan, $oleh, 'tutup', StatusLaporan::Selesai,
            AksiAudit::MenutupPengaduan, fn (): string => $catatan);
    }

    public function disposisi(
        Laporan $laporan,
        User $oleh,
        Unit $unit,
        User $pic,
        Prioritas $prioritas,
        ?string $instruksi,
    ): Laporan {
        if (! self::unitTujuanSah($oleh, $unit)) {
            throw AksiPengaduanTidakSah::unitTidakSah();
        }

        if (! self::picSah($pic, $unit)) {
            throw AksiPengaduanTidakSah::picTidakSah();
        }

        $disposisi = null;

        $laporan = $this->ubahStatus($laporan, $oleh, 'disposisi', StatusLaporan::Diproses,
            AksiAudit::MendisposisikanPengaduan,
            function (Laporan $terkunci) use ($oleh, $unit, $pic, $prioritas, $instruksi, &$disposisi): ?string {
                $kategori = $terkunci->kategori ?? throw AksiPengaduanTidakSah::kategoriBelumDitetapkan();
                $unitLama = $terkunci->unit;
                $catatanUnit = null;

                if ($unitLama?->isNot($unit)) {
                    $catatanUnit = "Unit diubah dari {$unitLama->nama} ke {$unit->nama}";
                    LogAudit::catat($oleh, AksiAudit::MengubahUnitPengaduan, $terkunci);
                }

                $terkunci->unit()->associate($unit);
                $terkunci->prioritas = $prioritas;
                $terkunci->batas_waktu = $kategori->batasSelesai($terkunci->dibuat_pada);

                $disposisi = $terkunci->disposisi()->create([
                    'unit_id' => $unit->getKey(),
                    'pic_user_id' => $pic->getKey(),
                    'instruksi' => filled($instruksi) ? trim($instruksi) : null,
                ]);

                return $catatanUnit;
            });

        PengaduanDidisposisikan::dispatch($disposisi);

        return $laporan;
    }

    /**
     * Admin Pengaduan yayasan boleh memilih unit aktif mana pun; Admin Sekolah hanya unitnya sendiri.
     */
    public static function unitTujuanSah(User $oleh, Unit $unit): bool
    {
        return $unit->aktif && (! $oleh->isAdminSekolah() || $oleh->unit_id === $unit->getKey());
    }

    public static function picSah(User $pic, Unit $unit): bool
    {
        return $pic->peran === Peran::PicUnit && $pic->unit_id === $unit->getKey() && ! $pic->terkunci();
    }

    /**
     * @param  (callable(Laporan): ?string)|null  $ubah  perubahan tambahan pada laporan yang sudah terkunci;
     *                                                   teks yang dikembalikan menjadi catatan khusus petugas
     * @param  string|null  $catatanPelapor  catatan yang boleh dibaca pelapor di halaman Lacak (H-07)
     */
    private function ubahStatus(
        Laporan $laporan,
        User $oleh,
        string $aksi,
        StatusLaporan $statusBaru,
        AksiAudit $aksiAudit,
        ?callable $ubah = null,
        ?string $catatanPelapor = null,
    ): Laporan {
        Gate::forUser($oleh)->authorize('kelolaPengaduan', $laporan);

        return $this->pengubah->ubah(
            $laporan,
            $oleh,
            fn (Laporan $terkunci): ?AksiPengaduanTidakSah => self::alasanTidakBisa($aksi, $terkunci),
            $statusBaru,
            $aksiAudit,
            $ubah,
            $catatanPelapor,
        );
    }
}
