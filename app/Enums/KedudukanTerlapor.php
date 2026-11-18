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
     * Peran yang menelaah dan memegang keputusan pada laporan (telaah, koreksi kedudukan, penunjukan investigator,
     * pembukaan identitas): Ketua Tim WBS, atau penelaah pengganti menurut matriks. Anggota Tim WBS tidak
     * termasuk; anggota bekerja lewat penunjukan sebagai investigator.
     *
     * @return list<Peran>
     */
    public function peranPenelaahUtama(): array
    {
        return array_values(array_filter($this->peranPenelaah(), fn (Peran $peran): bool => $peran !== Peran::AnggotaTimWbs));
    }

    /**
     * Urutan kedudukan untuk aturan "bila terlapor lebih dari satu, kedudukan tertinggi menentukan".
     */
    public function tingkat(): int
    {
        return match ($this) {
            self::TidakTahu => 0,
            self::GuruPegawai => 1,
            self::Pengurus => 2,
            self::SpiTimWbs => 3,
            self::Pengawas => 4,
            self::Pembina => 5,
        };
    }

    /**
     * Kedudukan yang tersirat bila pejabat dengan peran ini disebut sebagai terlapor (lapis kedua UC-02 2a;
     * anggota Dewan Pengawas diperlakukan sebagai Pengawas, Bagian 3.1).
     */
    public static function tersiratDariPeran(?Peran $peran): ?self
    {
        return match ($peran) {
            Peran::KetuaTimWbs, Peran::AnggotaTimWbs => self::SpiTimWbs,
            Peran::DewanPengawas => self::Pengawas,
            Peran::KetuaPembina, Peran::Pembina => self::Pembina,
            Peran::KetuaUmum, Peran::PengurusHarian => self::Pengurus,
            Peran::KepalaSekolah => self::GuruPegawai,
            default => null,
        };
    }

    /**
     * Peran penerima hasil menurut kolom "Penerima hasil" Bagian 3.1: menerima kesimpulan dan rekomendasi, dan menyetujui
     * perpanjangan investigasi (UC-13 1a). Rapat Pembina dijalankan Ketua Pembina dan Pembina. Untuk terlapor tidak
     * diketahui, perpanjangan disetujui Dewan Pengawas (Bagian 3.1, paragraf terakhir).
     *
     * @return list<Peran>
     */
    public function peranPenerimaHasil(): array
    {
        return match ($this) {
            self::GuruPegawai, self::TidakTahu => [Peran::DewanPengawas],
            self::Pengurus => [Peran::KetuaPembina],
            self::SpiTimWbs, self::Pengawas, self::Pembina => [Peran::KetuaPembina, Peran::Pembina],
        };
    }

    /**
     * Penerima tembusan hasil untuk tindak lanjut, tanpa wewenang menyetujui perpanjangan (Bagian 3.1, A7).
     *
     * @return list<Peran>
     */
    public function peranTembusanHasil(): array
    {
        return $this === self::GuruPegawai ? [Peran::KetuaUmum] : [];
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
