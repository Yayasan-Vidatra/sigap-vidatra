<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Wbs\KodeAksesWbs as LayananKodeAkses;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * H-19 Pengaturan > Kode Akses WBS (revisi 20-11, log deviasi Bagian 2): kode akses bersama kanal WBS internal dan
 * petunjuknya. Hanya Super Admin dan Ketua Tim WBS. Kode lama tidak pernah ditampilkan (hanya hash yang tersimpan);
 * isian kode selalu kosong dan hanya diisi bila kode diganti. Perubahan tercatat di riwayat_pengaturan tanpa
 * nilai kode (App\Services\Wbs\KodeAksesWbs).
 *
 * @property-read Schema $form
 */
class KodeAksesWbs extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Kode Akses WBS';

    protected static ?string $title = 'Kode Akses WBS';

    protected static ?string $slug = 'pengaturan/kode-akses-wbs';

    protected static ?int $navigationSort = 125;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && LayananKodeAkses::bolehMengelola($user);
    }

    public function mount(): void
    {
        $this->isiUlang();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Kode akses kanal WBS')
                    ->description('Pelapor memasukkan kode ini di halaman /wbs sebelum membaca jaminan kerahasiaan dan mengisi formulir. '
                        .'Bagikan alamat /wbs dan kodenya hanya lewat jalur internal.')
                    ->schema([
                        Text::make(fn (): string => LayananKodeAkses::dibuka()
                            ? 'Kode akses sudah diatur. Kode lama tidak dapat ditampilkan; isi kolom di bawah hanya bila ingin menggantinya.'
                            : 'Kode akses belum diatur, sehingga /wbs menampilkan "Kanal belum dibuka, hubungi Tim WBS".'),
                        TextInput::make('kode_baru')
                            ->label('Kode akses baru')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->minLength(LayananKodeAkses::PANJANG_MINIMAL)
                            ->maxLength(100)
                            ->helperText('Huruf besar dan kecil dianggap sama. Kosongkan bila tidak diganti.'),
                        TextInput::make('kode_baru_confirmation')
                            ->label('Ulangi kode akses baru')
                            ->password()
                            ->autocomplete('new-password')
                            ->same('kode_baru')
                            ->dehydrated(false)
                            ->requiredWith('kode_baru'),
                        Textarea::make('petunjuk')
                            ->label('Petunjuk kode akses')
                            ->rows(2)
                            ->maxLength(300)
                            ->helperText('Tampil di bawah isian kode di /wbs. Jangan menuliskan kodenya sendiri.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('simpan')
                ->footer([
                    Actions::make([
                        Action::make('simpan')->label('Simpan')->submit('simpan'),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function simpan(): void
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User && static::canAccess(), 403);

        $data = $this->form->getState();

        app(LayananKodeAkses::class)->simpan($user, $data['kode_baru'] ?? null, $data['petunjuk'] ?? null);

        $this->isiUlang();

        Notification::make()->success()->title('Pengaturan kode akses WBS tersimpan.')->send();
    }

    private function isiUlang(): void
    {
        $this->form->fill([
            'kode_baru' => null,
            'kode_baru_confirmation' => null,
            'petunjuk' => LayananKodeAkses::petunjuk(),
        ]);
    }
}
