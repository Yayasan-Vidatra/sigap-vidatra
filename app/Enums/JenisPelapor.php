<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Jenis pelapor pengaduan (UC-01 langkah 1).
 */
enum JenisPelapor: string implements HasLabel
{
    case OrangTua = 'orang_tua';
    case Siswa = 'siswa';
    case Alumni = 'alumni';
    case Masyarakat = 'masyarakat';

    public function getLabel(): string
    {
        return match ($this) {
            self::OrangTua => 'Orang tua',
            self::Siswa => 'Siswa',
            self::Alumni => 'Alumni',
            self::Masyarakat => 'Masyarakat',
        };
    }
}
