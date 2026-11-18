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
     * Peran yang boleh membuka laporan menurut kolom "Penelaah dan investigator" Bagian 3.1 (F-19). Perutean ini
     * tersimpan lewat laporan.kedudukan_terlapor sejak laporan dikirim. Pengecualian anggota yang terlibat
     * (anggota tim yang tidak terlibat, anggota Pembina yang tidak terlibat) ditambahkan bersama Ruang Kerja
     * WBS (Hari 10).
     *
     * @return list<Peran>
     */
    public function peranPenelaah(): array
    {
        return match ($this) {
            self::GuruPegawai, self::Pengurus, self::TidakTahu => [Peran::KetuaTimWbs, Peran::AnggotaTimWbs],
            self::SpiTimWbs => [Peran::DewanPengawas],
            self::Pengawas, self::Pembina => [Peran::KetuaPembina, Peran::Pembina],
        };
    }

    /**
     * Kolom "Penerima hasil" Bagian 3.1.
     */
    public function penerimaHasil(): string
    {
        return match ($this) {
            self::GuruPegawai => 'Dewan Pengawas, tembusan Ketua Umum untuk tindak lanjut',
            self::Pengurus => 'Ketua Pembina, tanpa tembusan kepada Pengurus',
            self::SpiTimWbs => 'Pembina',
            self::Pengawas, self::Pembina => 'Rapat Pembina',
            self::TidakTahu => 'Ditetapkan setelah terlapor teridentifikasi',
        };
    }

    /**
     * Nilai kedudukan yang laporannya boleh dibuka peran tertentu.
     *
     * @return list<string>
     */
    public static function nilaiTerbukaBagi(Peran $peran): array
    {
        return array_values(array_map(
            fn (self $kedudukan): string => $kedudukan->value,
            array_filter(self::cases(), fn (self $kedudukan): bool => in_array($peran, $kedudukan->peranPenelaah(), true)),
        ));
    }

    /**
     * Baris matriks yang tidak boleh dibuka Ketua Tim WBS (Bagian 3.1, aturan proyek no. 2).
     */
    public function tertutupBagiKetuaTimWbs(): bool
    {
        return in_array($this, [self::SpiTimWbs, self::Pengawas, self::Pembina], true);
    }

    /**
     * @return list<string>
     */
    public static function nilaiTertutupBagiKetuaTimWbs(): array
    {
        return array_values(array_map(
            fn (self $kedudukan): string => $kedudukan->value,
            array_filter(self::cases(), fn (self $kedudukan): bool => $kedudukan->tertutupBagiKetuaTimWbs()),
        ));
    }
}
