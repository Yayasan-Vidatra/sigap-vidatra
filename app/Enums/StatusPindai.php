<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Hasil pemindaian virus lampiran (UC-17).
 */
enum StatusPindai: string implements HasLabel
{
    case Menunggu = 'menunggu';
    case Bersih = 'bersih';
    case Karantina = 'karantina';

    public function getLabel(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu pindai',
            self::Bersih => 'Bersih',
            self::Karantina => 'Dikarantina',
        };
    }
}
