<?php

namespace App\Listeners;

use App\Enums\AksiAudit;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

/**
 * Mencatat login gagal di log audit dan mengunci akun pada kegagalan ke-5 (US-26, F-16).
 *
 * Filament memicu Failed bila kata sandi salah, atau bila kata sandinya benar tetapi akun tidak boleh
 * masuk (terkunci, tanpa peran, atau akun SSO yang memakai kata sandi).
 */
class CatatLoginGagal
{
    public function handle(Failed $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            LogAudit::catatObjek(null, AksiAudit::LoginGagal, LogAudit::OBJEK_PENGGUNA_TIDAK_TERDAFTAR);

            return;
        }

        if ($user->terkunci()) {
            LogAudit::catat($user, AksiAudit::LoginDitolakAkunTerkunci, $user);

            return;
        }

        $baruTerkunci = $user->catatGagalLogin();

        LogAudit::catat($user, AksiAudit::LoginGagal, $user);

        if ($baruTerkunci) {
            LogAudit::catat($user, AksiAudit::AkunTerkunci, $user);
        }
    }
}
