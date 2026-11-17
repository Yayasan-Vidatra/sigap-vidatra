<?php

namespace App\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\JenisLaporan;
use App\Enums\Peran;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Events\PengaduanDidisposisikan;
use App\Events\StatusLaporanBerubah;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Disposisi;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\RiwayatStatus;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Alur Admin Pengaduan di Kotak Masuk (H-11) menurut Rancangan r1 Bagian 6:
 * - verifikasi dan kategorisasi (UC-08): Diterima → Terverifikasi;
 * - tolak dengan alasan wajib (UC-11): Diterima atau Terverifikasi → Ditolak;
 * - disposisi ke unit, PIC, prioritas, dan instruksi (UC-09): Terverifikasi → Diproses, dengan batas
 *   waktu dari SLA kategori dalam hari kerja.
 *
 * Hak akses diperiksa di sini, bukan hanya di tombol. Setiap aksi mengunci baris laporan, memeriksa
 * status terbaru, mencatat riwayat_status dan log audit, lalu mengirim event setelah transaksi tersimpan.
 */
class PenangananPengaduan
{
    /**
     * Status asal yang sah untuk setiap aksi.
     *
     * @var array<string, list<StatusLaporan>>
     */
    public const STATUS_ASAL = [
        'verifikasi' => [StatusLaporan::Diterima],
        'tolak' => [StatusLaporan::Diterima, StatusLaporan::Terverifikasi],
        'disposisi' => [StatusLaporan::Terverifikasi],
    ];

    public static function bisa(string $aksi, Laporan $laporan): bool
    {
        return in_array($laporan->status, self::STATUS_ASAL[$aksi], true);
    }

    public function verifikasi(Laporan $laporan, User $oleh, Kategori $kategori): Laporan
    {
        if ($kategori->jenis !== JenisLaporan::Pengaduan) {
            throw AksiPengaduanTidakSah::kategoriBukanPengaduan();
        }

        return $this->ubahStatus($laporan, $oleh, 'verifikasi', 'diverifikasi', StatusLaporan::Terverifikasi,
            AksiAudit::MemverifikasiPengaduan, function (Laporan $terkunci) use ($kategori): void {
                $terkunci->kategori()->associate($kategori);
            });
    }

    public function tolak(Laporan $laporan, User $oleh, string $alasan): Laporan
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw AksiPengaduanTidakSah::alasanWajib();
        }

        return $this->ubahStatus($laporan, $oleh, 'tolak', 'ditolak', StatusLaporan::Ditolak,
            AksiAudit::MenolakPengaduan, catatan: $alasan);
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

        $laporan = $this->ubahStatus($laporan, $oleh, 'disposisi', 'didisposisikan', StatusLaporan::Diproses,
            AksiAudit::MendisposisikanPengaduan,
            function (Laporan $terkunci) use ($unit, $pic, $prioritas, $instruksi, &$disposisi): void {
                $kategori = $terkunci->kategori ?? throw AksiPengaduanTidakSah::kategoriBelumDitetapkan();

                $terkunci->unit()->associate($unit);
                $terkunci->prioritas = $prioritas;
                $terkunci->batas_waktu = $kategori->batasSelesai($terkunci->dibuat_pada);

                $disposisi = $terkunci->disposisi()->create([
                    'unit_id' => $unit->getKey(),
                    'pic_user_id' => $pic->getKey(),
                    'instruksi' => filled($instruksi) ? trim($instruksi) : null,
                ]);
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
     * @param  (callable(Laporan): void)|null  $ubah  perubahan tambahan pada laporan yang sudah terkunci
     */
    private function ubahStatus(
        Laporan $laporan,
        User $oleh,
        string $aksi,
        string $kataKerja,
        StatusLaporan $statusBaru,
        AksiAudit $aksiAudit,
        ?callable $ubah = null,
        ?string $catatan = null,
    ): Laporan {
        Gate::forUser($oleh)->authorize('kelolaPengaduan', $laporan);

        return DB::transaction(function () use ($laporan, $oleh, $aksi, $kataKerja, $statusBaru, $aksiAudit, $ubah, $catatan): Laporan {
            $terkunci = Laporan::query()->lockForUpdate()->findOrFail($laporan->getKey());

            if (! self::bisa($aksi, $terkunci)) {
                throw AksiPengaduanTidakSah::statusTidakSesuai($kataKerja, $terkunci->status);
            }

            $statusLama = $terkunci->status;

            if ($ubah !== null) {
                $ubah($terkunci);
            }

            $terkunci->status = $statusBaru;
            $terkunci->save();

            /** @var RiwayatStatus $riwayat */
            $riwayat = $terkunci->riwayatStatus()->create([
                'status_lama' => $statusLama,
                'status_baru' => $statusBaru,
                'oleh_user_id' => $oleh->getKey(),
                'catatan' => $catatan,
            ]);

            LogAudit::catat($oleh, $aksiAudit, $terkunci);

            StatusLaporanBerubah::dispatch($terkunci, $riwayat);

            return $terkunci;
        });
    }
}
