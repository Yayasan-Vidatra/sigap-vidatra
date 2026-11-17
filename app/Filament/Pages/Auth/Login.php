<?php

namespace App\Filament\Pages\Auth;

use App\Enums\JenisAkun;
use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * Login petugas H-09 (US-26). Kata sandi hanya untuk akun lokal; akun SSO masuk lewat Google
 * Workspace. Kegagalan memicu event Failed, yang dipakai untuk penguncian dan log audit.
 */
class Login extends BaseLogin
{
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        return parent::isUserAllowedToAccessPanel($user)
            && $user instanceof User
            && $user->jenis_akun === JenisAkun::Lokal;
    }

    /**
     * Pesan yang sama untuk semua kegagalan agar tidak membocorkan apakah akun ada atau terkunci.
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Surel atau kata sandi salah, atau akun ini tidak dapat login dengan kata sandi. '
                .'Akun terkunci setelah 5 kali gagal; hubungi Super Admin untuk membukanya.',
        ]);
    }
}
