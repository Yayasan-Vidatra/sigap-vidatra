<?php

namespace App\Filament\Resources\LogAudits;

use App\Filament\Resources\LogAudits\Pages\ListLogAudits;
use App\Filament\Resources\LogAudits\Tables\LogAuditsTable;
use App\Models\LogAudit;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * H-20 Log Audit (US-27, F-16): hanya baca. Super Admin membaca semua baris, Ketua Tim WBS hanya log
 * laporan WBS yang boleh ia buka. Akses ditegakkan lewat LogAuditPolicy dan scope terlihatOleh().
 */
class LogAuditResource extends Resource
{
    protected static ?string $model = LogAudit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Log Audit';

    protected static ?string $modelLabel = 'log audit';

    protected static ?string $pluralModelLabel = 'log audit';

    protected static ?string $slug = 'log-audit';

    /**
     * @return Builder<LogAudit>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        return parent::getEloquentQuery()
            ->with('user')
            ->when(
                $user instanceof User,
                fn (Builder $query) => $query->terlihatOleh($user),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            );
    }

    public static function table(Table $table): Table
    {
        return LogAuditsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLogAudits::route('/'),
        ];
    }
}
