<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\TautanBukti;
use App\Models\User;

/**
 * Siapa yang boleh membuka tautan bukti, sama dengan lampiran (LampiranPolicy): WBS menurut matriks penerima
 * (bukaLaporanWbs), pengaduan menurut Admin Pengaduan dan PIC Unit. Super Admin tidak pernah membuka tautan WBS
 * (aturan proyek no. 3). Tautan tidak dapat diubah atau dihapus setelah terkirim.
 */
class TautanBuktiPolicy
{
    public function view(User $user, TautanBukti $tautanBukti): bool
    {
        $laporan = $tautanBukti->laporan;

        if ($laporan->isWbs()) {
            return $user->can('bukaLaporanWbs', $laporan);
        }

        return match ($user->peran) {
            Peran::AdminPengaduan => $user->can('kelolaPengaduan', $laporan),
            // PIC tidak melihat kanal pesan (UC-05), termasuk tautan yang dikirim lewat pesan.
            Peran::PicUnit => $tautanBukti->pesan_id === null && $user->can('tindakLanjutiPengaduan', $laporan),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, TautanBukti $tautanBukti): bool
    {
        return false;
    }

    public function delete(User $user, TautanBukti $tautanBukti): bool
    {
        return false;
    }
}
