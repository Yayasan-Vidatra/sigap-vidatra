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
    case MembukaTautanBukti = 'membuka_tautan_bukti';
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
    case MemulaiTelaahWbs = 'memulai_telaah_wbs';
    case MengoreksiKedudukanTerlapor = 'mengoreksi_kedudukan_terlapor';
    case MenghentikanLaporanWbs = 'menghentikan_laporan_wbs';
    case MenunjukInvestigator = 'menunjuk_investigator';
    case MenetapkanKesimpulanWbs = 'menetapkan_kesimpulan_wbs';
    case MencatatLaporanMasuk = 'mencatat_laporan_masuk';
    case MengajukanPerpanjangan = 'mengajukan_perpanjangan';
    case MenyetujuiPerpanjangan = 'menyetujui_perpanjangan';
    case MenolakPerpanjangan = 'menolak_perpanjangan';
    case MembukaHasilWbs = 'membuka_hasil_wbs';
    case MenandaiHasilDitindaklanjuti = 'menandai_hasil_ditindaklanjuti';
    case MengalihkanLaporan = 'mengalihkan_laporan';
    case MembukaPengalihan = 'membuka_pengalihan';
    case MengonfirmasiPengalihan = 'mengonfirmasi_pengalihan';
    case MencatatTindakLanjutPengalihan = 'mencatat_tindak_lanjut_pengalihan';
    case MemindahkanPengaduanKeWbs = 'memindahkan_pengaduan_ke_wbs';
    case MembuatPinSementaraPelapor = 'membuat_pin_sementara_pelapor';
    case MengeksporRekap = 'mengekspor_rekap';
    case MengubahPengaturan = 'mengubah_pengaturan';
    case MemberiPeranKhusus = 'memberi_peran_khusus';
    case MencabutPeranKhusus = 'mencabut_peran_khusus';
    case MengaturUlang2fa = 'mengatur_ulang_2fa';
    case MengirimTautanKataSandi = 'mengirim_tautan_kata_sandi';
    case MengaturKataSandiLewatTautan = 'mengatur_kata_sandi_lewat_tautan';
    case MengubahKataSandi = 'mengubah_kata_sandi';

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
            self::MembukaTautanBukti => 'Membuka tautan bukti (Google Drive)',
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
            self::MemulaiTelaahWbs => 'Memulai telaah WBS',
            self::MengoreksiKedudukanTerlapor => 'Mengoreksi kedudukan terlapor',
            self::MenghentikanLaporanWbs => 'Menyatakan WBS tidak ditindaklanjuti',
            self::MenunjukInvestigator => 'Menunjuk investigator WBS',
            self::MenetapkanKesimpulanWbs => 'Menetapkan kesimpulan WBS',
            self::MencatatLaporanMasuk => 'Mencatat laporan masuk (surel/telepon/surat)',
            self::MengajukanPerpanjangan => 'Mengajukan perpanjangan investigasi',
            self::MenyetujuiPerpanjangan => 'Menyetujui perpanjangan investigasi',
            self::MenolakPerpanjangan => 'Menolak perpanjangan investigasi',
            self::MembukaHasilWbs => 'Membuka hasil WBS',
            self::MenandaiHasilDitindaklanjuti => 'Menandai hasil WBS sudah ditindaklanjuti',
            self::MengalihkanLaporan => 'Mengalihkan laporan',
            self::MembukaPengalihan => 'Membuka laporan dialihkan',
            self::MengonfirmasiPengalihan => 'Mengonfirmasi terima pengalihan',
            self::MencatatTindakLanjutPengalihan => 'Mencatat tindak lanjut pengalihan',
            self::MemindahkanPengaduanKeWbs => 'Memindahkan pengaduan ke WBS',
            self::MembuatPinSementaraPelapor => 'Membuat PIN sementara pelapor',
            self::MengeksporRekap => 'Mengekspor rekap',
            self::MengubahPengaturan => 'Mengubah pengaturan',
            self::MemberiPeranKhusus => 'Memberi peran Tim WBS/penerima hasil/Super Admin',
            self::MencabutPeranKhusus => 'Mencabut peran Tim WBS/penerima hasil/Super Admin',
            self::MengaturUlang2fa => 'Mengatur ulang 2FA',
            self::MengirimTautanKataSandi => 'Mengirim tautan buat kata sandi',
            self::MengaturKataSandiLewatTautan => 'Membuat kata sandi lewat tautan',
            self::MengubahKataSandi => 'Mengubah kata sandi sendiri',
        };
    }
}
