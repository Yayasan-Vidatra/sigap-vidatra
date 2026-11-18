<?php

namespace App\Filament\Resources\PengaduanUnit;

use App\Filament\Resources\PengaduanUnit\Pages\ListPengaduanUnit;
use App\Filament\Resources\PengaduanUnit\Pages\ViewPengaduanUnit;
use App\Filament\Resources\PengaduanUnit\Schemas\PengaduanUnitInfolist;
use App\Filament\Resources\PengaduanUnit\Tables\PengaduanUnitTable;
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
 * H-12 Pengaduan Unit Saya (US-12; UC-10; F-09). Untuk PIC Unit: hanya pengaduan yang sudah didisposisikan
 * ke unitnya. Lingkup ditegakkan di query (scope Laporan::pengaduanUnitPic) dan di LaporanPolicy
 * (lihatPengaduanUnit, tindakLanjutiPengaduan), sehingga alamat pengaduan lain memberi 404.
 */
class PengaduanUnitResource extends Resource
{
    protected static ?string $model = Laporan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Pengaduan Unit Saya';

    protected static ?string $modelLabel = 'pengaduan unit';

    protected static ?string $pluralModelLabel = 'pengaduan unit saya';

    protected static ?string $slug = 'pengaduan-unit';

    protected static ?string $recordTitleAttribute = 'kode_lacak';

    /**
     * @return Builder<Laporan>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        return parent::getEloquentQuery()
            ->with(['kategori'])
            ->when(
                $user instanceof User,
                fn (Builder $query) => $query->pengaduanUnitPic($user),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            );
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return Gate::forUser(Filament::auth()->user())->inspect('lihatPengaduanUnit', Laporan::class);
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return Gate::forUser(Filament::auth()->user())->inspect('tindakLanjutiPengaduan', $record);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PengaduanUnitInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PengaduanUnitTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengaduanUnit::route('/'),
            'view' => ViewPengaduanUnit::route('/{record}'),
        ];
    }
}
