<?php

namespace App\Filament\Resources\HasilWbs\Pages;

use App\Filament\Resources\HasilWbs\HasilWbsResource;
use Filament\Resources\Pages\ListRecords;

class ListHasilWbs extends ListRecords
{
    protected static string $resource = HasilWbsResource::class;

    public function getTitle(): string
    {
        return 'Hasil WBS untuk Saya';
    }
}
