<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\User;
use App\Services\Pengaturan\PengelolaDataJabatan;

/**
 * Data jabatan di Pengaturan (H-19, US-24): hanya Super Admin, dan hanya untuk akun Admin, PIC, Kepala Sekolah,
 * Komite Etik, atau akun nonaktif. Akun Tim WBS, penerima hasil, dan Super Admin tidak bisa dibuka atau diubah
 * di sini (Rancangan r1 Bagian 9). Akun tidak dihapus karena dirujuk log audit; akun dinonaktifkan.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->peran === Peran::SuperAdmin;
    }

    public function view(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->viewAny($user) && PengelolaDataJabatan::bolehDikelola($model);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
