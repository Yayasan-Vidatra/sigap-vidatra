<?php

namespace App\Filament\Resources\Units;

use App\Filament\Resources\Units\Pages\ManageUnits;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaturan\PengelolaDataJabatan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * H-19 Pengaturan, Unit dan kontak darurat (US-24, UC-15). Hanya Super Admin (UnitPolicy); perubahan lewat
 * PengelolaDataJabatan sehingga tercatat di log audit. Tidak ada aksi hapus.
 */
class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Unit dan Kontak Darurat';

    protected static ?string $modelLabel = 'unit';

    protected static ?string $pluralModelLabel = 'unit';

    protected static ?string $slug = 'pengaturan/unit';

    protected static ?int $navigationSort = 110;

    protected static ?string $recordTitleAttribute = 'nama';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->label('Nama unit')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('kontak_darurat')
                    ->label('Kontak darurat sekolah')
                    ->helperText('Tampil di formulir WBS bila laporan menyangkut kekerasan (UC-02 2b).')
                    ->maxLength(255),
                Toggle::make('aktif')
                    ->label('Aktif (bisa dipilih pelapor)')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->columns([
                TextColumn::make('nama')->label('Unit')->searchable(),
                TextColumn::make('kontak_darurat')->label('Kontak darurat')->placeholder('-'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (Unit $record, array $data): Unit => app(PengelolaDataJabatan::class)
                        ->simpanUnit(self::pengguna(), $record, $data)),
            ])
            ->toolbarActions([]);
    }

    public static function pengguna(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUnits::route('/'),
        ];
    }
}
