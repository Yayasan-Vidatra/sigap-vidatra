<?php

namespace App\Filament\Resources\LaporanWbs;

use App\Filament\Resources\LaporanWbs\Pages\CatatLaporanMasuk;
use App\Filament\Resources\LaporanWbs\Pages\ListLaporanWbs;
use App\Filament\Resources\LaporanWbs\Pages\ViewLaporanWbs;
use App\Filament\Resources\LaporanWbs\Schemas\LaporanWbsInfolist;
use App\Filament\Resources\LaporanWbs\Tables\LaporanWbsTable;
use App\Models\Laporan;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * H-13 Ruang Kerja WBS (US-14–US-17; UC-12, UC-13; F-10, F-19) dan H-14 Catat Laporan Masuk (US-18, UC-18, F-17).
 *
 * Lingkup data ditegakkan di query (scope Laporan::wbsTerbukaBagi) dan di LaporanPolicy (lihatRuangKerjaWbs,
 * bukaLaporanWbs), sehingga laporan yang tertutup menurut matriks tidak muncul di daftar dan alamat langsungnya
 * memberi 404. Resource ini tidak ikut pencarian global, agar kode lacak WBS tidak pernah muncul di sana.
 */
class LaporanWbsResource extends Resource
{
    protected static ?string $model = Laporan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Ruang Kerja WBS';

    protected static ?string $modelLabel = 'laporan WBS';

    protected static ?string $pluralModelLabel = 'laporan WBS';

    protected static ?string $slug = 'wbs';

    protected static ?string $recordTitleAttribute = 'kode_lacak';

    protected static bool $isGloballySearchable = false;

    /**
     * @return Builder<Laporan>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        return parent::getEloquentQuery()
            ->with(['unit', 'kategori', 'investigator'])
            ->when(
                $user instanceof User,
                fn (Builder $query) => $query->wbsTerbukaBagi($user),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            );
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return Gate::forUser(Filament::auth()->user())->inspect('lihatRuangKerjaWbs', Laporan::class);
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return Gate::forUser(Filament::auth()->user())->inspect('bukaLaporanWbs', $record);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LaporanWbsInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LaporanWbsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLaporanWbs::route('/'),
            'catat' => CatatLaporanMasuk::route('/catat'),
            'view' => ViewLaporanWbs::route('/{record}'),
        ];
    }
}
