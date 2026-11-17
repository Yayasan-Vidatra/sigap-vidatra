<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Kanal laporan (Rancangan r1 Bagian 2). Dipakai juga untuk kolom kategori.jenis.
 */
enum JenisLaporan: string implements HasLabel
{
    case Pengaduan = 'pengaduan';
    case Wbs = 'wbs';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pengaduan => 'Pengaduan',
            self::Wbs => 'Pelaporan Pelanggaran (WBS)',
        };
    }
}
