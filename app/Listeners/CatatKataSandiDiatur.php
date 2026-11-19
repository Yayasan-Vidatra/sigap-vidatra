<?php

namespace App\Listeners;

use App\Enums\AksiAudit;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Mencatat kata sandi yang dibuat lewat tautan sekali pakai (sigap:peran-khusus). Hanya siapa dan kapan; kata sandi
 * maupun hash-nya tidak pernah dicatat.
 */
class CatatKataSandiDiatur
{
    public function handle(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            LogAudit::catat($event->user, AksiAudit::MengaturKataSandiLewatTautan, $event->user);
        }
    }
}
