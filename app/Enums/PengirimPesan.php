<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Pengirim pesan pada kanal pesan laporan (UC-05). Sistem dipakai untuk pemberitahuan otomatis
 * kepada pelapor, misalnya lampiran yang dikarantina (UC-01 alur 5a).
 */
enum PengirimPesan: string implements HasLabel
{
    case Pelapor = 'pelapor';
    case Petugas = 'petugas';
    case Sistem = 'sistem';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pelapor => 'Pelapor',
            self::Petugas => 'Petugas',
            self::Sistem => 'Sistem',
        };
    }
}
