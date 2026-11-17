<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\User;

/**
 * Siapa yang boleh membuka lampiran laporan (Rancangan r1 Bagian 3, 3.1, dan 8). Sementara halaman
 * laporan petugas (H-11 sampai H-13) belum ada, aturannya sengaja sempit:
 * - pengaduan: Admin Pengaduan yayasan; Admin Sekolah dan PIC Unit hanya untuk unitnya;
 * - WBS: Ketua Tim WBS, hanya untuk laporan yang terbuka baginya menurut matriks.
 * Peran lain ditolak, termasuk Super Admin untuk WBS (aturan proyek no. 3). Diperhalus bersama policy
 * laporan saat halaman laporan dibuat (penunjukan investigator, penelaah pengganti, pengalihan).
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
            Peran::AdminPengaduan => $user->isAdminYayasan() || $user->unit_id === $laporan->unit_id,
            Peran::PicUnit => $user->unit_id !== null && $user->unit_id === $laporan->unit_id,
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
