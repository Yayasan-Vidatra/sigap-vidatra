<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * H-10 Dasbor (US-22): kartu ringkasan sesuai peran, daftar "Perlu tindakan Anda", lalu grafik bagi peran yang
 * berhak. Semua widget berbagi satu kolom penuh agar urutannya sama di layar lebar dan di HP; kartu ringkasan
 * menyusun dirinya sendiri (satu kolom di HP).
 */
class Dasbor extends Dashboard
{
    /**
     * Nama route tetap filament.panel.pages.dashboard seperti dasbor bawaan; alamatnya tetap /panel.
     */
    protected static ?string $slug = 'dashboard';

    /**
     * @return int|array<string, int|null>
     */
    public function getColumns(): int|array
    {
        return 1;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Dasbor';
    }

    public function getSubheading(): ?string
    {
        return 'Ringkasan laporan yang menjadi bagian Anda.';
    }
}
