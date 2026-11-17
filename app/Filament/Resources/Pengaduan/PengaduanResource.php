<?php

namespace App\Filament\Resources\Pengaduan;

use App\Filament\Resources\Pengaduan\Pages\ListPengaduan;
use App\Filament\Resources\Pengaduan\Pages\ViewPengaduan;
use App\Filament\Resources\Pengaduan\Schemas\PengaduanInfolist;
use App\Filament\Resources\Pengaduan\Tables\PengaduanTable;
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
 * H-11 Kotak Masuk Pengaduan (US-10, US-11; UC-08, UC-09, UC-11; F-07, F-08, F-11). Hanya laporan jenis
 * pengaduan; laporan WBS tidak pernah muncul. Lingkup data ditegakkan di query (scope
 * Laporan::kotakMasukPengaduan) dan di LaporanPolicy (lihatKotakMasukPengaduan, kelolaPengaduan), sehingga
 * Admin Sekolah yang membuka alamat pengaduan unit lain mendapat 404.
 */
class PengaduanResource extends Resource
{
    protected static ?string $model = Laporan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $navigationLabel = 'Kotak Masuk Pengaduan';

    protected static ?string $modelLabel = 'pengaduan';

    protected static ?string $pluralModelLabel = 'pengaduan';

    protected static ?string $slug = 'pengaduan';

    protected static ?string $recordTitleAttribute = 'kode_lacak';

    /**
     * @return Builder<Laporan>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        return parent::getEloquentQuery()
            ->with(['unit', 'kategori'])
            ->when(
                $user instanceof User,
                fn (Builder $query) => $query->kotakMasukPengaduan($user),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            );
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return Gate::forUser(Filament::auth()->user())->inspect('lihatKotakMasukPengaduan', Laporan::class);
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return Gate::forUser(Filament::auth()->user())->inspect('kelolaPengaduan', $record);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PengaduanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PengaduanTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengaduan::route('/'),
            'view' => ViewPengaduan::route('/{record}'),
        ];
    }
}
