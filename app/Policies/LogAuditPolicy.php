<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\LogAudit;
use App\Models\User;

/**
 * H-20: Super Admin (lihat saja) dan Ketua Tim WBS (khusus laporan WBS). Log audit hanya bisa ditambah
 * oleh sistem, sehingga tidak ada pengguna yang boleh membuat, mengubah, atau menghapusnya (NF-07).
 */
class LogAuditPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->peran, [Peran::SuperAdmin, Peran::KetuaTimWbs], true);
    }

    public function view(User $user, LogAudit $logAudit): bool
    {
        return $this->viewAny($user)
            && LogAudit::query()->terlihatOleh($user)->whereKey($logAudit->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, LogAudit $logAudit): bool
    {
        return false;
    }

    public function delete(User $user, LogAudit $logAudit): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, LogAudit $logAudit): bool
    {
        return false;
    }

    public function forceDelete(User $user, LogAudit $logAudit): bool
    {
        return false;
    }
}
