<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Jenis akun petugas (kolom users.jenis_akun, Rancangan r1 Bagian 9).
 */
enum JenisAkun: string implements HasLabel
{
    case Sso = 'sso';
    case Lokal = 'lokal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sso => 'SSO Google Workspace',
            self::Lokal => 'Akun lokal',
        };
    }
}
