<?php

namespace App\Filament\Support;

use App\Enums\StatusLaporan;
use Filament\Support\Colors\Color;

/**
 * Palet panel petugas sesuai prototipe yang disetujui (prd/mockup/panel-kotak-masuk.html dan
 * panel-ruang-kerja-wbs.html): biru #1E3A9E untuk warna utama, biru tua #0B1B4D untuk area WBS, dan satu warna
 * badge per status. Merah hanya dipakai untuk tindakan menolak dan tanda lewat batas waktu.
 */
class WarnaPanel
{
    /**
     * Biru utama; #1E3A9E di shade 600 (tombol dan teks aktif).
     *
     * @var array<int, string>
     */
    public const UTAMA = [
        50 => '#eef2fc',
        100 => '#e3e9fa',
        200 => '#c9d4f2',
        300 => '#a5b6e8',
        400 => '#8196dd',
        500 => '#4a64c7',
        600 => '#1e3a9e',
        700 => '#142a78',
        800 => '#0f2160',
        900 => '#0b1a4a',
        950 => '#07112f',
    ];

    /**
     * Biru tua area WBS; #0B1B4D di shade 600.
     *
     * @var array<int, string>
     */
    public const WBS = [
        50 => '#eef1f8',
        100 => '#dde3f1',
        200 => '#c7d2ee',
        300 => '#9fb0db',
        400 => '#8396cf',
        500 => '#3a4f99',
        600 => '#0b1b4d',
        700 => '#08143a',
        800 => '#060f2c',
        900 => '#050d27',
        950 => '#030817',
    ];

    /**
     * Semua palet yang didaftarkan ke panel. Nama `status-*` dipakai badge status; gayanya disetel di
     * resources/css/filament/panel/theme.css agar sama dengan prototipe.
     *
     * @return array<string, array<int, string>>
     */
    public static function palet(): array
    {
        return [
            'primary' => self::UTAMA,
            'wbs' => self::WBS,
            'danger' => Color::Red,
            'gray' => Color::Slate,
            'info' => Color::Sky,
            'success' => Color::Green,
            'warning' => Color::Amber,
            'status-diterima' => self::UTAMA,
            'status-diproses' => Color::Amber,
            'status-menunggu' => Color::Violet,
            'status-selesai' => Color::Green,
            'status-ditolak' => Color::Red,
            'status-dipindah' => self::WBS,
            'status-ditelaah' => Color::Sky,
            'status-abu' => Color::Slate,
        ];
    }

    /**
     * Warna badge status laporan, sama untuk pengaduan dan WBS: Diterima biru, Diproses dan Diinvestigasi kuning,
     * Menunggu konfirmasi ungu, Selesai hijau, Ditolak merah, Dipindah ke WBS abu-biru tua, Ditelaah (dan
     * Terverifikasi) biru muda, Tidak ditindaklanjuti serta status pengalihan abu-abu.
     */
    public static function status(StatusLaporan $status): string
    {
        return match ($status) {
            StatusLaporan::Diterima => 'status-diterima',
            StatusLaporan::Terverifikasi, StatusLaporan::Ditelaah => 'status-ditelaah',
            StatusLaporan::Diproses, StatusLaporan::Diinvestigasi, StatusLaporan::DiinvestigasiDiperpanjang => 'status-diproses',
            StatusLaporan::MenungguKonfirmasi => 'status-menunggu',
            StatusLaporan::Selesai => 'status-selesai',
            StatusLaporan::Ditolak => 'status-ditolak',
            StatusLaporan::DipindahKeWbs => 'status-dipindah',
            StatusLaporan::TidakDitindaklanjuti,
            StatusLaporan::DialihkanKeSekolah,
            StatusLaporan::DirujukKePokjaBsan,
            StatusLaporan::DialihkanKeKomiteEtik => 'status-abu',
        };
    }
}
