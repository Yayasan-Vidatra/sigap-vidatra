<?php

namespace App\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\StatusLaporan;
use App\Events\StatusLaporanBerubah;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\RiwayatStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Langkah bersama setiap perubahan status pengaduan (Rancangan r1 Bagian 6): mengunci baris laporan,
 * memeriksa status terbaru, menjalankan perubahan tambahan, mencatat riwayat_status dan log audit, lalu
 * mengirim StatusLaporanBerubah setelah transaksi tersimpan. Hak akses diperiksa oleh pemanggil.
 */
class PengubahStatus
{
    /**
     * @param  User|null  $oleh  null bila diubah pelapor atau sistem
     * @param  callable(Laporan): ?AksiPengaduanTidakSah  $periksa  alasan aksi tidak boleh dijalankan pada status terbaru
     * @param  (callable(Laporan): ?string)|null  $ubah  perubahan tambahan pada laporan yang sudah terkunci; teks
     *                                                   yang dikembalikan menjadi catatan khusus petugas
     * @param  string|null  $catatanPelapor  catatan yang boleh dibaca pelapor di halaman Lacak (H-07)
     * @param  bool  $beritahuPelapor  false bila pelapor sendiri yang mengubah status, sehingga tidak perlu surel
     */
    public function ubah(
        Laporan $laporan,
        ?User $oleh,
        callable $periksa,
        StatusLaporan $statusBaru,
        ?AksiAudit $aksiAudit,
        ?callable $ubah = null,
        ?string $catatanPelapor = null,
        bool $beritahuPelapor = true,
    ): Laporan {
        return DB::transaction(function () use ($laporan, $oleh, $periksa, $statusBaru, $aksiAudit, $ubah, $catatanPelapor, $beritahuPelapor): Laporan {
            $terkunci = Laporan::query()->lockForUpdate()->findOrFail($laporan->getKey());

            if ($galat = $periksa($terkunci)) {
                throw $galat;
            }

            $statusLama = $terkunci->status;
            $catatanPetugas = $ubah !== null ? $ubah($terkunci) : null;

            $terkunci->status = $statusBaru;
            $terkunci->save();

            /** @var RiwayatStatus $riwayat */
            $riwayat = $terkunci->riwayatStatus()->create([
                'status_lama' => $statusLama,
                'status_baru' => $statusBaru,
                'oleh_user_id' => $oleh?->getKey(),
                'catatan' => $catatanPelapor ?? $catatanPetugas,
                'tampil_ke_pelapor' => $catatanPelapor !== null,
            ]);

            if ($aksiAudit !== null) {
                LogAudit::catat($oleh, $aksiAudit, $terkunci);
            }

            StatusLaporanBerubah::dispatch($terkunci, $riwayat, $beritahuPelapor);

            return $terkunci;
        });
    }

    /**
     * Pemeriksaan status asal yang umum: aksi hanya boleh dari salah satu status ini.
     *
     * @param  list<StatusLaporan>  $statusAsal
     * @return callable(Laporan): ?AksiPengaduanTidakSah
     */
    public static function dariStatus(array $statusAsal, string $kataKerja): callable
    {
        return fn (Laporan $laporan): ?AksiPengaduanTidakSah => in_array($laporan->status, $statusAsal, true)
            ? null
            : AksiPengaduanTidakSah::statusTidakSesuai($kataKerja, $laporan->status);
    }
}
