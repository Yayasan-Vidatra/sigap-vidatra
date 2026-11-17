<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Prioritas disposisi pengaduan (UC-09).
 */
enum Prioritas: string implements HasLabel
{
    case Rendah = 'rendah';
    case Sedang = 'sedang';
    case Tinggi = 'tinggi';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rendah => 'Rendah',
            self::Sedang => 'Sedang',
            self::Tinggi => 'Tinggi',
        };
    }
}
