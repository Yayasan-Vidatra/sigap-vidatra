<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Kesimpulan investigasi WBS (UC-13).
 */
enum KesimpulanWbs: string implements HasLabel
{
    case Terbukti = 'terbukti';
    case TidakTerbukti = 'tidak_terbukti';
    case BuktiTidakCukup = 'bukti_tidak_cukup';

    public function getLabel(): string
    {
        return match ($this) {
            self::Terbukti => 'Terbukti',
            self::TidakTerbukti => 'Tidak terbukti',
            self::BuktiTidakCukup => 'Bukti tidak cukup',
        };
    }
}
