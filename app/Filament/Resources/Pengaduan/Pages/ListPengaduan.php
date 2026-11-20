<?php

namespace App\Filament\Resources\Pengaduan\Pages;

use App\Enums\StatusLaporan;
use App\Filament\Concerns\MenampilkanTombolStatus;
use App\Filament\Resources\Pengaduan\PengaduanResource;
use Filament\Resources\Pages\ListRecords;

class ListPengaduan extends ListRecords
{
    use MenampilkanTombolStatus;

    protected static string $resource = PengaduanResource::class;

    public function getTitle(): string
    {
        return 'Kotak Masuk Pengaduan';
    }

    public function getSubheading(): ?string
    {
        return 'Pengaduan yang menjadi bagian Anda. Klik baris untuk membuka.';
    }

    /**
     * @return array<string, string>
     */
    protected function pilihanTombolStatus(): array
    {
        return [
            StatusLaporan::Diterima->value => 'Diterima',
            StatusLaporan::Terverifikasi->value => 'Terverifikasi',
            StatusLaporan::Diproses->value => 'Diproses',
            StatusLaporan::MenungguKonfirmasi->value => 'Menunggu konfirmasi',
            StatusLaporan::Selesai->value => 'Selesai',
            StatusLaporan::Ditolak->value => 'Ditolak',
            StatusLaporan::DipindahKeWbs->value => 'Dipindah ke WBS',
        ];
    }
}
