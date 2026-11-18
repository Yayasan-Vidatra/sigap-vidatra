<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Enums\TujuanPengalihan;
use App\Models\Pengalihan;
use App\Models\User;
use App\Services\Wbs\AksesWbs;

/**
 * H-17 Laporan Dialihkan (US-19, US-20): Kepala Sekolah hanya pengalihan ke unitnya dan tidak pernah laporan yang
 * menyangkut dirinya (penanda kepala_sekolah_terlapor atau namanya disebut sebagai terlapor WBS); Komite Etik hanya
 * pengalihan ke Komite Etik. Peran lain, termasuk Super Admin, ditolak.
 */
class PengalihanPolicy
{
    public function viewAny(User $user): bool
    {
        return ($user->peran === Peran::KepalaSekolah && $user->unit_id !== null) || $user->peran === Peran::KomiteEtik;
    }

    public function view(User $user, Pengalihan $pengalihan): bool
    {
        $laporan = $pengalihan->laporan;

        if ($laporan->isWbs() && AksesWbs::disebutSebagaiTerlapor($user, $laporan)) {
            return false;
        }

        return match ($user->peran) {
            Peran::KepalaSekolah => $pengalihan->tujuan === TujuanPengalihan::KepalaSekolah
                && $user->unit_id !== null
                && $pengalihan->unit_tujuan_id === $user->unit_id
                && ! $pengalihan->kepala_sekolah_terlapor,
            Peran::KomiteEtik => $pengalihan->tujuan === TujuanPengalihan::KomiteEtik,
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Pengalihan $pengalihan): bool
    {
        return false;
    }

    public function delete(User $user, Pengalihan $pengalihan): bool
    {
        return false;
    }
}
