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

    /**
     * Driver pemindai "lokal" (Windows/Laragon tanpa ClamAV): berkas tidak diperiksa sama sekali.
     */
    case TidakDipindai = 'tidak_dipindai';

    /**
     * Pemindaian gagal setelah beberapa percobaan; perlu diperiksa pengelola TI.
     */
    case Gagal = 'gagal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu pindai',
            self::Bersih => 'Bersih',
            self::Karantina => 'Dikarantina',
            self::TidakDipindai => 'Tidak dipindai',
            self::Gagal => 'Pemindaian gagal',
        };
    }
}
