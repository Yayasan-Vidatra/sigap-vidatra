<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tujuan pengalihan laporan (UC-19, kolom pengalihan.tujuan dan laporan.dialihkan_ke).
 */
enum TujuanPengalihan: string implements HasLabel
{
    case KepalaSekolah = 'kepala_sekolah';
    case KomiteEtik = 'komite_etik';
    case PokjaBsan = 'pokja_bsan';

    public function getLabel(): string
    {
        return match ($this) {
            self::KepalaSekolah => 'Kepala Sekolah unit',
            self::KomiteEtik => 'Komite Etik',
            self::PokjaBsan => 'Pokja BSAN',
        };
    }
}
