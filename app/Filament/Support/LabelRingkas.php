<?php

namespace App\Filament\Support;

use App\Enums\KedudukanTerlapor;
use App\Enums\SumberLaporan;

/**
 * Label pendek untuk kolom tabel yang sempit (prototipe panel). Label lengkap tetap dipakai di formulir, filter,
 * dan halaman detail.
 */
class LabelRingkas
{
    public static function kedudukan(?KedudukanTerlapor $kedudukan): string
    {
        return match ($kedudukan) {
            KedudukanTerlapor::GuruPegawai => 'Guru/pegawai',
            KedudukanTerlapor::Pengurus => 'Pengurus',
            KedudukanTerlapor::SpiTimWbs => 'SPI/Tim WBS',
            KedudukanTerlapor::Pengawas => 'Pengawas',
            KedudukanTerlapor::Pembina => 'Pembina',
            KedudukanTerlapor::TidakTahu, null => 'Tidak tahu',
            KedudukanTerlapor::LainLain => 'Lain-lain',
        };
    }

    public static function sumber(?SumberLaporan $sumber): string
    {
        return match ($sumber) {
            SumberLaporan::Laman => 'Laman',
            SumberLaporan::Surel => 'Surel',
            SumberLaporan::Telepon => 'Telepon/WA',
            SumberLaporan::Surat => 'Surat',
            SumberLaporan::Pengaduan => 'Pindahan',
            null => '-',
        };
    }

    /**
     * Pilihan filter dengan label pendek, berkunci nilai enum.
     *
     * @return array<string, string>
     */
    public static function pilihanKedudukan(): array
    {
        return collect(KedudukanTerlapor::cases())
            ->mapWithKeys(fn (KedudukanTerlapor $kedudukan): array => [$kedudukan->value => self::kedudukan($kedudukan)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function pilihanSumber(): array
    {
        return collect(SumberLaporan::cases())
            ->mapWithKeys(fn (SumberLaporan $sumber): array => [$sumber->value => self::sumber($sumber)])
            ->all();
    }
}
