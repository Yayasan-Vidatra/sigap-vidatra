<?php

namespace App\Listeners;

use App\Enums\AksiAudit;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Mencatat login berhasil (kata sandi + 2FA, atau SSO + 2FA) dan mengosongkan hitungan gagal login.
 */
class CatatLoginBerhasil
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->resetGagalLogin();

        LogAudit::catat($event->user, AksiAudit::LoginBerhasil, $event->user);
    }
}
