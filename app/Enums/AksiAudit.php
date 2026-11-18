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
    case MembukaLampiran = 'membuka_lampiran';
    case LampiranDikarantina = 'lampiran_dikarantina';
    case MembukaIdentitasPelapor = 'membuka_identitas_pelapor';
    case MemverifikasiPengaduan = 'memverifikasi_pengaduan';
    case MenolakPengaduan = 'menolak_pengaduan';
    case MendisposisikanPengaduan = 'mendisposisikan_pengaduan';
    case MengubahUnitPengaduan = 'mengubah_unit_pengaduan';
    case MenutupPengaduan = 'menutup_pengaduan';
    case MencatatTindakLanjut = 'mencatat_tindak_lanjut';
    case MengunggahBuktiPenyelesaian = 'mengunggah_bukti_penyelesaian';
    case MemintaKonfirmasiPelapor = 'meminta_konfirmasi_pelapor';
    case PelaporMengonfirmasiHasil = 'pelapor_mengonfirmasi_hasil';
    case PelaporMenolakHasil = 'pelapor_menolak_hasil';
    case MenutupPengaduanOtomatis = 'menutup_pengaduan_otomatis';
    case MengirimPengingatBatasWaktu = 'mengirim_pengingat_batas_waktu';
    case MengirimEskalasiBatasWaktu = 'mengirim_eskalasi_batas_waktu';
    case PelaporMembukaLaporan = 'pelapor_membuka_laporan';
    case PelaporMengirimPesan = 'pelapor_mengirim_pesan';
    case MembalasPesan = 'membalas_pesan';
    case PelaporMemberiPenilaian = 'pelapor_memberi_penilaian';

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
            self::MembukaLampiran => 'Membuka lampiran',
            self::LampiranDikarantina => 'Lampiran dikarantina',
            self::MembukaIdentitasPelapor => 'Membuka identitas pelapor',
            self::MemverifikasiPengaduan => 'Memverifikasi pengaduan',
            self::MenolakPengaduan => 'Menolak pengaduan',
            self::MendisposisikanPengaduan => 'Mendisposisikan pengaduan',
            self::MengubahUnitPengaduan => 'Mengubah unit pengaduan',
            self::MenutupPengaduan => 'Menutup pengaduan (konfirmasi lewat telepon)',
            self::MencatatTindakLanjut => 'Mencatat tindak lanjut',
            self::MengunggahBuktiPenyelesaian => 'Mengunggah bukti penyelesaian',
            self::MemintaKonfirmasiPelapor => 'Meminta konfirmasi pelapor',
            self::PelaporMengonfirmasiHasil => 'Pelapor mengonfirmasi hasil',
            self::PelaporMenolakHasil => 'Pelapor menolak hasil',
            self::MenutupPengaduanOtomatis => 'Menutup pengaduan otomatis (7 hari tanpa tanggapan)',
            self::MengirimPengingatBatasWaktu => 'Mengirim pengingat batas waktu',
            self::MengirimEskalasiBatasWaktu => 'Mengirim eskalasi batas waktu',
            self::PelaporMembukaLaporan => 'Pelapor membuka laporan (Lacak)',
            self::PelaporMengirimPesan => 'Pelapor mengirim pesan',
            self::MembalasPesan => 'Petugas mengirim pesan',
            self::PelaporMemberiPenilaian => 'Pelapor memberi penilaian',
        };
    }
}
