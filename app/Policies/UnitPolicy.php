<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\Unit;
use App\Models\User;

/**
 * Unit dan kontak darurat di Pengaturan (H-19, US-24): hanya Super Admin. Unit tidak dihapus karena dirujuk
 * laporan dan pengguna; unit yang tidak dipakai lagi dinonaktifkan.
 */
class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->peran === Peran::SuperAdmin;
    }

    public function view(User $user, Unit $unit): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Unit $unit): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Unit $unit): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Unit $unit): bool
    {
        return false;
    }

    public function forceDelete(User $user, Unit $unit): bool
    {
        return false;
    }
}
