<?php

namespace App\Filament\Resources\LaporanWbs\Pages;

use App\Filament\Resources\LaporanWbs\LaporanWbsResource;
use App\Models\Laporan;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLaporanWbs extends ListRecords
{
    protected static string $resource = LaporanWbsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('catat')
                ->label('Catat laporan masuk')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->authorize('catatLaporanMasuk', Laporan::class)
                ->url(LaporanWbsResource::getUrl('catat')),
        ];
    }
}
