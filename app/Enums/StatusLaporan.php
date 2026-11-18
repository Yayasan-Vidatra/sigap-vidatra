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
     * Penjelasan singkat status pengaduan untuk pelapor (surel dan halaman Lacak).
     */
    public function penjelasanUntukPelapor(): string
    {
        return match ($this) {
            self::Diterima => 'Pengaduan Anda sudah masuk dan menunggu diperiksa petugas.',
            self::Terverifikasi => 'Pengaduan Anda sudah diperiksa dan akan diteruskan ke unit terkait.',
            self::Diproses => 'Pengaduan Anda sedang ditangani unit terkait.',
            self::MenungguKonfirmasi => 'Unit sudah menindaklanjuti pengaduan Anda. Mohon buka halaman Lacak Laporan untuk '
                .'mengonfirmasi atau menolak hasilnya dalam '.config('sigap.pengaduan.hari_konfirmasi').' hari. Tanpa tanggapan, '
                .'pengaduan dianggap selesai.',
            self::Selesai => 'Penanganan pengaduan Anda sudah selesai. Terima kasih atas laporan Anda.',
            self::Ditolak => 'Pengaduan Anda tidak dapat ditindaklanjuti.',
            default => 'Status pengaduan Anda diperbarui.',
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
