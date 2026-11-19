<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;

/**
 * Permintaan tautan kata sandi mandiri sengaja dimatikan: tautan hanya dikirim lewat `sigap:peran-khusus` dengan
 * persetujuan Ketua Dewan Pengawas, agar pemegang kotak surel (misalnya admin tenant) tidak bisa mengambil alih akun
 * lokal (Rancangan r1 Bagian 9). Filament mewajibkan halaman ini ada supaya halaman buat kata sandi terdaftar.
 */
class MintaTautanKataSandi extends RequestPasswordReset
{
    public function mount(): void
    {
        abort(404);
    }
}
