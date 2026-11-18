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
    case Pengaduan = 'pengaduan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Laman => 'Laman',
            self::Surel => 'Surel',
            self::Telepon => 'Telepon/WhatsApp',
            self::Surat => 'Surat tertutup',
            self::Pengaduan => 'Pindahan dari Pengaduan',
        };
    }

    /**
     * Saluran yang dicatat Ketua Tim WBS lewat Catat Laporan Masuk (H-14, UC-18).
     *
     * @return list<self>
     */
    public static function saluranLaporanMasuk(): array
    {
        return [self::Surel, self::Telepon, self::Surat];
    }
}
