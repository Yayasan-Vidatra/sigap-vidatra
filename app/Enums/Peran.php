<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Peran akun petugas (Rancangan r1 Bagian 3). A1, A2 (pelapor) dan A9 (sistem) tidak memiliki akun.
 */
enum Peran: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case AdminPengaduan = 'admin_pengaduan';
    case PicUnit = 'pic_unit';
    case KetuaTimWbs = 'ketua_tim_wbs';
    case AnggotaTimWbs = 'anggota_tim_wbs';
    case DewanPengawas = 'dewan_pengawas';
    case KetuaPembina = 'ketua_pembina';
    case Pembina = 'pembina';
    case KetuaUmum = 'ketua_umum';
    case PengurusHarian = 'pengurus_harian';
    case KepalaSekolah = 'kepala_sekolah';
    case KomiteEtik = 'komite_etik';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AdminPengaduan => 'Admin Pengaduan',
            self::PicUnit => 'PIC Unit',
            self::KetuaTimWbs => 'Ketua Tim WBS (SPI)',
            self::AnggotaTimWbs => 'Anggota Tim WBS',
            self::DewanPengawas => 'Dewan Pengawas',
            self::KetuaPembina => 'Ketua Pembina',
            self::Pembina => 'Pembina',
            self::KetuaUmum => 'Ketua Umum',
            self::PengurusHarian => 'Pengurus Harian',
            self::KepalaSekolah => 'Kepala Sekolah',
            self::KomiteEtik => 'Komite Etik',
        };
    }

    /**
     * Kode aktor di Rancangan r1 Bagian 3; Super Admin tidak memiliki kode aktor.
     */
    public function aktor(): ?string
    {
        return match ($this) {
            self::SuperAdmin => null,
            self::AdminPengaduan => 'A3',
            self::PicUnit => 'A4',
            self::KetuaTimWbs, self::AnggotaTimWbs => 'A5',
            self::DewanPengawas, self::KetuaPembina, self::Pembina => 'A6',
            self::KetuaUmum, self::PengurusHarian => 'A7',
            self::KepalaSekolah, self::KomiteEtik => 'A8',
        };
    }

    /**
     * SSO Google Workspace hanya untuk Admin Pengaduan dan PIC Unit; peran lain memakai akun lokal
     * (Rancangan r1 Bagian 9 dan 11, TOR H-09).
     */
    public function bolehSso(): bool
    {
        return in_array($this, [self::AdminPengaduan, self::PicUnit], true);
    }

    /**
     * Peran pemegang data jabatan yang dikelola Super Admin di Pengaturan (H-19, US-24): Admin, PIC, Kepala Sekolah,
     * dan Komite Etik. Peran Tim WBS, penerima hasil, dan Super Admin tidak diberikan lewat Pengaturan karena
     * pemberiannya memerlukan persetujuan Ketua Dewan Pengawas (Rancangan r1 Bagian 9).
     */
    public function dikelolaLewatPengaturan(): bool
    {
        return in_array($this, self::kelolaanPengaturan(), true);
    }

    /**
     * @return list<self>
     */
    public static function kelolaanPengaturan(): array
    {
        return [self::AdminPengaduan, self::PicUnit, self::KepalaSekolah, self::KomiteEtik];
    }

    /**
     * Peran yang wajib terikat pada satu unit (A4, dan Kepala Sekolah pada A8).
     */
    public function wajibUnit(): bool
    {
        return in_array($this, [self::PicUnit, self::KepalaSekolah], true);
    }

    /**
     * Peran yang boleh memiliki unit_id. Admin Pengaduan dengan unit_id adalah Admin Sekolah
     * (Rancangan r1 Bagian 2) yang hanya menangani unit itu; tanpa unit_id berarti seluruh yayasan.
     */
    public function bolehUnit(): bool
    {
        return $this->wajibUnit() || $this === self::AdminPengaduan;
    }
}
