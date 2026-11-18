<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\User;

/**
 * Siapa yang boleh membuka lampiran laporan (Rancangan r1 Bagian 3, 3.1, dan 8):
 * - pengaduan: mengikuti LaporanPolicy. Admin Pengaduan yayasan semua unit, Admin Sekolah hanya unitnya
 *   (kelolaPengaduan), PIC Unit hanya pengaduan yang sudah didisposisikan ke unitnya
 *   (tindakLanjutiPengaduan), termasuk bukti penyelesaiannya;
 * - WBS: Ketua Tim WBS, hanya untuk laporan yang terbuka baginya menurut matriks.
 * Peran lain ditolak, termasuk Super Admin untuk WBS (aturan proyek no. 3). Aturan WBS diperhalus bersama
 * Ruang Kerja WBS (penunjukan investigator, penelaah pengganti, pengalihan).
 */
class LampiranPolicy
{
    public function view(User $user, Lampiran $lampiran): bool
    {
        $laporan = $lampiran->laporan;

        if ($laporan->isWbs()) {
            return $user->peran === Peran::KetuaTimWbs
                && Laporan::query()->terbukaBagiKetuaTimWbs()->whereKey($laporan->getKey())->exists();
        }

        return match ($user->peran) {
            Peran::AdminPengaduan => $user->can('kelolaPengaduan', $laporan),
            Peran::PicUnit => $user->can('tindakLanjutiPengaduan', $laporan),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Lampiran $lampiran): bool
    {
        return false;
    }

    /**
     * Lampiran tidak dapat dihapus setelah terkirim, agar bukti tetap utuh (Rancangan r1 Bagian 8).
     */
    public function delete(User $user, Lampiran $lampiran): bool
    {
        return false;
    }

    public function restore(User $user, Lampiran $lampiran): bool
    {
        return false;
    }

    public function forceDelete(User $user, Lampiran $lampiran): bool
    {
        return false;
    }
}
