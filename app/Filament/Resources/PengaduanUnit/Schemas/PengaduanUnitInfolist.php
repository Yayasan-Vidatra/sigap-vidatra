<?php

namespace App\Filament\Resources\PengaduanUnit\Schemas;

use App\Filament\Resources\Pengaduan\Schemas\PengaduanInfolist;
use Filament\Schemas\Schema;

/**
 * Detail pengaduan untuk PIC Unit (H-12). Tanpa identitas pelapor: komunikasi dengan pelapor lewat Admin
 * dan kanal pesan (UC-05 tidak melibatkan PIC). Catatan riwayat yang hanya untuk Admin tidak ditampilkan.
 */
class PengaduanUnitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                PengaduanInfolist::ringkasan(),
                PengaduanInfolist::isi(),
                PengaduanInfolist::lampiran('lampiranPelapor', 'Lampiran pelapor', 'Tidak ada lampiran.'),
                PengaduanInfolist::tautanBukti(),
                PengaduanInfolist::disposisi(),
                PengaduanInfolist::lampiran('buktiPenyelesaian', 'Bukti penyelesaian', 'Belum ada bukti penyelesaian.'),
                PengaduanInfolist::riwayat(denganCatatanPetugas: false),
            ]);
    }
}
