<?php

namespace App\Filament\Resources\PengaduanUnit\Pages;

use App\Filament\Resources\PengaduanUnit\PengaduanUnitResource;
use Filament\Resources\Pages\ListRecords;

class ListPengaduanUnit extends ListRecords
{
    protected static string $resource = PengaduanUnitResource::class;

    public function getTitle(): string
    {
        return 'Pengaduan Unit Saya';
    }
}
