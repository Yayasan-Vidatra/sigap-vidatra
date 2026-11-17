<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Saluran asal laporan (kolom laporan.sumber, Rancangan r1 Bagian 10 dan UC-18).
 */
enum SumberLaporan: string implements HasLabel
{
    case Laman = 'laman';
    case Surel = 'surel';
    case Telepon = 'telepon';
    case Surat = 'surat';

    public function getLabel(): string
    {
        return match ($this) {
            self::Laman => 'Laman',
            self::Surel => 'Surel',
            self::Telepon => 'Telepon/WhatsApp',
            self::Surat => 'Surat tertutup',
        };
    }
}
