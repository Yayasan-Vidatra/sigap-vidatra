<?php

namespace App\Filament\Resources\LogAudits\Pages;

use App\Filament\Resources\LogAudits\LogAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListLogAudits extends ListRecords
{
    protected static string $resource = LogAuditResource::class;
}
