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
     * Peran yang hak aksesnya dibatasi pada satu unit (A4, dan Kepala Sekolah pada A8).
     */
    public function terikatUnit(): bool
    {
        return in_array($this, [self::PicUnit, self::KepalaSekolah], true);
    }
}
