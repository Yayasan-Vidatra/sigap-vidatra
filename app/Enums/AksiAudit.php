<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Aksi yang dicatat di log audit (F-16, US-27).
 */
enum AksiAudit: string implements HasLabel
{
    case LoginBerhasil = 'login_berhasil';
    case LoginGagal = 'login_gagal';
    case LoginDitolakAkunTerkunci = 'login_ditolak_akun_terkunci';
    case AkunTerkunci = 'akun_terkunci';
    case KunciAkunDibuka = 'kunci_akun_dibuka';
    case LoginSsoDitolak = 'login_sso_ditolak';
    case MembukaLaporan = 'membuka_laporan';

    public function getLabel(): string
    {
        return match ($this) {
            self::LoginBerhasil => 'Login berhasil',
            self::LoginGagal => 'Login gagal',
            self::LoginDitolakAkunTerkunci => 'Login ditolak: akun terkunci',
            self::AkunTerkunci => 'Akun terkunci (5 kali gagal)',
            self::KunciAkunDibuka => 'Kunci akun dibuka',
            self::LoginSsoDitolak => 'Login SSO ditolak',
            self::MembukaLaporan => 'Membuka laporan',
        };
    }
}
