<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Pengirim pesan pada kanal pesan laporan (UC-05).
 */
enum PengirimPesan: string implements HasLabel
{
    case Pelapor = 'pelapor';
    case Petugas = 'petugas';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pelapor => 'Pelapor',
            self::Petugas => 'Petugas',
        };
    }
}
