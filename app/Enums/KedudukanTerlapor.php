<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Pilihan "kedudukan terlapor" pada formulir WBS (Rancangan r1 Bagian 3.1).
 */
enum KedudukanTerlapor: string implements HasLabel
{
    case GuruPegawai = 'guru_pegawai';
    case Pengurus = 'pengurus';
    case SpiTimWbs = 'spi_tim_wbs';
    case Pengawas = 'pengawas';
    case Pembina = 'pembina';
    case TidakTahu = 'tidak_tahu';

    public function getLabel(): string
    {
        return match ($this) {
            self::GuruPegawai => 'Guru, pegawai, Kepala Sekolah, pejabat unit',
            self::Pengurus => 'Pengurus, termasuk Ketua Umum',
            self::SpiTimWbs => 'SPI atau anggota Tim WBS',
            self::Pengawas => 'Pengawas',
            self::Pembina => 'Pembina',
            self::TidakTahu => 'Tidak tahu',
        };
    }

    /**
     * Baris matriks yang tidak boleh dibuka Ketua Tim WBS (Bagian 3.1, aturan proyek no. 2).
     */
    public function tertutupBagiKetuaTimWbs(): bool
    {
        return in_array($this, [self::SpiTimWbs, self::Pengawas, self::Pembina], true);
    }
}
