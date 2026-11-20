<?php

namespace App\Filament\Resources\Users;

use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Support\TabelPanel;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaturan\PengelolaDataJabatan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/**
 * H-19 Pengaturan, Pengguna dan Jabatan (US-24, UC-15): nama dan surel Admin, PIC, Kepala Sekolah, dan Komite Etik.
 * Hanya Super Admin (UserPolicy). Akun Tim WBS, penerima hasil, dan Super Admin tidak termasuk query ini sehingga
 * tidak bisa dibuka atau diubah, juga lewat URL atau aksi Livewire langsung. Pergantian pejabat: buat akun baru,
 * lalu nonaktifkan akun lama (peran dikosongkan), agar kata sandi dan 2FA tidak berpindah tangan.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Pengguna dan Jabatan';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    protected static ?string $slug = 'pengaturan/pengguna';

    protected static ?int $navigationSort = 100;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('unit')
            ->where(fn (Builder $query) => $query
                ->whereNull('peran')
                ->orWhereIn('peran', array_map(fn (Peran $peran): string => $peran->value, Peran::kelolaanPengaturan())));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Surel')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->rules(fn (Get $get): array => self::jenisAkun($get('jenis_akun')) === JenisAkun::Sso
                        ? ['ends_with:@'.config('sigap.sso.domain')]
                        : []),
                Select::make('peran')
                    ->label('Peran')
                    ->options(collect(Peran::kelolaanPengaturan())
                        ->mapWithKeys(fn (Peran $peran): array => [$peran->value => $peran->getLabel()])
                        ->all())
                    ->placeholder('Nonaktif (tanpa akses panel)')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->live(),
                Select::make('unit_id')
                    ->label('Unit')
                    ->options(fn (): array => Unit::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->helperText('Admin Pengaduan tanpa unit menangani seluruh yayasan.')
                    ->visible(fn (Get $get): bool => self::peran($get('peran'))?->bolehUnit() ?? false)
                    ->required(fn (Get $get): bool => self::peran($get('peran'))?->wajibUnit() ?? false),
                Select::make('jenis_akun')
                    ->label('Jenis akun')
                    ->options(JenisAkun::class)
                    ->default(JenisAkun::Lokal->value)
                    ->selectablePlaceholder(false)
                    ->helperText('SSO Google Workspace hanya untuk Admin Pengaduan dan PIC Unit.')
                    ->visible(fn (Get $get): bool => self::peran($get('peran'))?->bolehSso() ?? false)
                    ->live(),
                TextInput::make('password')
                    ->label('Kata sandi')
                    ->password()
                    ->revealable()
                    ->rule(Password::defaults())
                    ->helperText(fn (string $operation): string => $operation === 'create'
                        ? 'Kata sandi awal akun lokal; sampaikan langsung kepada pemegang akun.'
                        : 'Kosongkan bila tidak diganti.')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->visible(fn (Get $get): bool => self::jenisAkun($get('jenis_akun')) !== JenisAkun::Sso
                        || ! (self::peran($get('peran'))?->bolehSso() ?? false)),
            ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable()->wrap(),
                TextColumn::make('email')->label('Surel')->searchable()->wrap(),
                TextColumn::make('peran')->label('Peran')->badge()->wrap()->color('gray')->placeholder('Nonaktif')->width('190px'),
                TextColumn::make('unit.nama')->label('Unit')->placeholder('–')->wrap()->width('140px'),
                TextColumn::make('jenis_akun')->label('Jenis akun')->wrap()->width('150px'),
                IconColumn::make('terkunci_pada')
                    ->label('Terkunci')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedLockClosed)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->state(fn (User $record): bool => $record->terkunci())
                    ->width('96px'),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('peran')
                    ->label('Peran')
                    ->options(collect(Peran::kelolaanPengaturan())
                        ->mapWithKeys(fn (Peran $peran): array => [$peran->value => $peran->getLabel()])
                        ->all()),
                Filter::make('terkunci')
                    ->label('Akun terkunci')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('terkunci_pada')),
            ])
            ->recordActions([
                // Seluruh baris membuka formulir ubah; tombol ubah tampil sebagai panah seperti tabel lain.
                EditAction::make()
                    ->iconButton()
                    ->icon(Heroicon::ChevronRight)
                    ->color('gray')
                    ->using(fn (User $record, array $data): User => app(PengelolaDataJabatan::class)
                        ->simpanPengguna(self::pengguna(), $record, $data)),
                Action::make('bukaKunci')
                    ->label('Buka kunci')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('warning')
                    ->visible(fn (User $record): bool => $record->terkunci())
                    ->requiresConfirmation()
                    ->modalDescription('Akun ini terkunci setelah 5 kali gagal login. Pastikan pemiliknya yang meminta pembukaan.')
                    ->action(function (User $record): void {
                        app(PengelolaDataJabatan::class)->bukaKunci(self::pengguna(), $record);

                        Notification::make()->success()->title('Kunci akun sudah dibuka.')->send();
                    }),
            ])
            ->toolbarActions([]);

        return TabelPanel::terapkan($table, 'ringkas', 'pengguna');
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
            'index' => ManageUsers::route('/'),
        ];
    }

    private static function peran(mixed $state): ?Peran
    {
        return $state instanceof Peran ? $state : Peran::tryFrom((string) $state);
    }

    private static function jenisAkun(mixed $state): ?JenisAkun
    {
        return $state instanceof JenisAkun ? $state : JenisAkun::tryFrom((string) $state);
    }
}
