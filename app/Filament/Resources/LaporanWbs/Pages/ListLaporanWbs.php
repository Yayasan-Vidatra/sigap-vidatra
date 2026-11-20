<?php

namespace App\Filament\Resources\LaporanWbs\Pages;

use App\Enums\StatusLaporan;
use App\Filament\Concerns\MenampilkanTombolStatus;
use App\Filament\Resources\LaporanWbs\LaporanWbsResource;
use App\Models\Laporan;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLaporanWbs extends ListRecords
{
    use MenampilkanTombolStatus;

    protected static string $resource = LaporanWbsResource::class;

    public function getTitle(): string
    {
        return 'Ruang Kerja WBS';
    }

    public function getSubheading(): ?string
    {
        return 'Hanya laporan yang menjadi bagian Anda menurut matriks penerima.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('catat')
                ->label('Catat laporan masuk')
                ->icon(Heroicon::OutlinedPlus)
                ->color('wbs')
                ->authorize('catatLaporanMasuk', Laporan::class)
                ->url(LaporanWbsResource::getUrl('catat')),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function pilihanTombolStatus(): array
    {
        return [
            StatusLaporan::Diterima->value => 'Diterima',
            StatusLaporan::Ditelaah->value => 'Ditelaah',
            StatusLaporan::Diinvestigasi->value => 'Diinvestigasi',
            StatusLaporan::Selesai->value => 'Selesai',
            StatusLaporan::TidakDitindaklanjuti->value => 'Tidak ditindaklanjuti',
        ];
    }
}
