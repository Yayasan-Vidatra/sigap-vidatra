<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Status laporan yang terlihat pelapor (Rancangan r1 Bagian 6).
 */
enum StatusLaporan: string implements HasLabel
{
    case Diterima = 'diterima';
    case Terverifikasi = 'terverifikasi';
    case Diproses = 'diproses';
    case MenungguKonfirmasi = 'menunggu_konfirmasi';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';
    case DipindahKeWbs = 'dipindah_ke_wbs';
    case Ditelaah = 'ditelaah';
    case Diinvestigasi = 'diinvestigasi';
    case DiinvestigasiDiperpanjang = 'diinvestigasi_diperpanjang';
    case TidakDitindaklanjuti = 'tidak_ditindaklanjuti';
    case DialihkanKeSekolah = 'dialihkan_ke_sekolah';
    case DirujukKePokjaBsan = 'dirujuk_ke_pokja_bsan';
    case DialihkanKeKomiteEtik = 'dialihkan_ke_komite_etik';

    public function getLabel(): string
    {
        return match ($this) {
            self::Diterima => 'Diterima',
            self::Terverifikasi => 'Terverifikasi',
            self::Diproses => 'Diproses',
            self::MenungguKonfirmasi => 'Menunggu Konfirmasi',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Ditolak',
            self::DipindahKeWbs => 'Dipindah ke WBS',
            self::Ditelaah => 'Ditelaah',
            self::Diinvestigasi => 'Diinvestigasi',
            self::DiinvestigasiDiperpanjang => 'Diinvestigasi (diperpanjang)',
            self::TidakDitindaklanjuti => 'Tidak Ditindaklanjuti',
            self::DialihkanKeSekolah => 'Dialihkan ke sekolah (BSAN)',
            self::DirujukKePokjaBsan => 'Dirujuk ke Pokja BSAN',
            self::DialihkanKeKomiteEtik => 'Dialihkan ke Komite Etik',
        };
    }

    /**
     * Label status di halaman dan surel pelapor. Sejak revisi 20-11 kanal WBS hanya untuk internal, sehingga halaman
     * publik tidak menyebut WBS: pengaduan yang dipindah tampil sebagai dialihkan ke tim penanganan khusus.
     */
    public function labelUntukPelapor(): string
    {
        return $this === self::DipindahKeWbs ? 'Dialihkan ke tim khusus' : $this->getLabel();
    }

    /**
     * Penjelasan singkat status untuk pelapor (surel pengaduan dan halaman Lacak).
     */
    public function penjelasanUntukPelapor(JenisLaporan $jenis = JenisLaporan::Pengaduan): string
    {
        if ($jenis === JenisLaporan::Wbs) {
            return $this->penjelasanUntukPelaporWbs();
        }

        return match ($this) {
            self::Diterima => 'Pengaduan Anda sudah masuk dan menunggu diperiksa petugas.',
            self::Terverifikasi => 'Pengaduan Anda sudah diperiksa dan akan diteruskan ke unit terkait.',
            self::Diproses => 'Pengaduan Anda sedang ditangani unit terkait.',
            self::MenungguKonfirmasi => 'Unit sudah menindaklanjuti pengaduan Anda. Mohon buka halaman Lacak Laporan untuk '
                .'mengonfirmasi atau menolak hasilnya dalam '.config('sigap.pengaduan.hari_konfirmasi').' hari. Tanpa tanggapan, '
                .'pengaduan dianggap selesai.',
            self::Selesai => 'Penanganan pengaduan Anda sudah selesai. Terima kasih atas laporan Anda.',
            self::Ditolak => 'Pengaduan Anda tidak dapat ditindaklanjuti.',
            self::DipindahKeWbs => 'Pengaduan Anda perlu ditangani tim khusus yayasan secara rahasia. Tim tersebut akan menghubungi Anda '
                .'melalui kontak yang Anda berikan.',
            self::DialihkanKeSekolah => 'Pengaduan Anda menyangkut kekerasan dan diteruskan ke Kepala Sekolah selaku penanggung jawab BSAN.',
            self::DirujukKePokjaBsan => 'Pengaduan Anda dirujuk ke Pokja BSAN.',
            self::DialihkanKeKomiteEtik => 'Pengaduan Anda menyangkut kode etik atau kepegawaian dan dialihkan ke Komite Etik.',
            default => 'Status pengaduan Anda diperbarui.',
        };
    }

    /**
     * Arti status WBS bagi pelapor (Bagian 6), tanpa menyebut nama petugas atau isi penanganan.
     */
    private function penjelasanUntukPelaporWbs(): string
    {
        return match ($this) {
            self::Diterima => 'Laporan Anda sudah masuk dan menunggu ditelaah.',
            self::Ditelaah => 'Laporan Anda sedang dinilai kelayakannya.',
            self::Diinvestigasi => 'Laporan Anda sedang diinvestigasi.',
            self::DiinvestigasiDiperpanjang => 'Investigasi diperpanjang dengan persetujuan penerima hasil.',
            self::Selesai => 'Penanganan sudah selesai dan ditutup dengan kesimpulan. Tindak lanjutnya diputuskan penerima hasil.',
            self::TidakDitindaklanjuti => 'Laporan tidak dapat ditindaklanjuti dengan investigasi. Alasan umumnya tercantum di riwayat status.',
            self::DialihkanKeSekolah => 'Laporan menyangkut kekerasan dan diteruskan ke Kepala Sekolah selaku penanggung jawab BSAN.',
            self::DirujukKePokjaBsan => 'Laporan dirujuk ke Pokja BSAN.',
            self::DialihkanKeKomiteEtik => 'Laporan menyangkut kode etik atau kepegawaian dan dialihkan ke Komite Etik.',
            default => 'Status laporan Anda diperbarui.',
        };
    }

    /**
     * Apakah status ini boleh dipakai pada kanal tertentu (kolom "Kanal" di Bagian 6).
     */
    public function berlakuUntuk(JenisLaporan $jenis): bool
    {
        return match ($this) {
            self::Terverifikasi,
            self::Diproses,
            self::MenungguKonfirmasi,
            self::Ditolak,
            self::DipindahKeWbs => $jenis === JenisLaporan::Pengaduan,

            self::Ditelaah,
            self::Diinvestigasi,
            self::DiinvestigasiDiperpanjang,
            self::TidakDitindaklanjuti => $jenis === JenisLaporan::Wbs,

            self::Diterima,
            self::Selesai,
            self::DialihkanKeSekolah,
            self::DirujukKePokjaBsan,
            self::DialihkanKeKomiteEtik => true,
        };
    }

    /**
     * @return list<self>
     */
    public static function untuk(JenisLaporan $jenis): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => $status->berlakuUntuk($jenis),
        ));
    }
}
