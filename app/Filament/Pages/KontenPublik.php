<?php

namespace App\Filament\Pages;

use App\Enums\Peran;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\Pengaturan\PengelolaKontenPublik;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * H-19 Pengaturan > Konten Publik (US-25, H-03, H-08): berkas jaminan kerahasiaan untuk file viewer /wbs, saluran
 * alternatif WBS, FAQ, dan Kebijakan Privasi. Hanya Super Admin; penyimpanan lewat PengelolaKontenPublik sehingga
 * tercatat di log audit. Berkas disimpan di disk privat dengan nama acak.
 *
 * @property-read Schema $form
 */
class KontenPublik extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Konten Publik';

    protected static ?string $title = 'Konten Publik';

    protected static ?string $slug = 'pengaturan/konten-publik';

    protected static ?int $navigationSort = 120;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->peran === Peran::SuperAdmin;
    }

    public function mount(): void
    {
        $this->form->fill(self::kePetaFormulir(PengelolaKontenPublik::isian()));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Jaminan kerahasiaan WBS')
                    ->description('Berkas PDF ini langsung tampil di file viewer halaman /wbs setelah disimpan.')
                    ->schema([
                        FileUpload::make('berkas_jaminan')
                            ->label('Berkas jaminan kerahasiaan (PDF)')
                            ->disk(PengelolaKontenPublik::DISK)
                            ->directory(PengelolaKontenPublik::FOLDER_BERKAS)
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->downloadable(false)
                            ->openable(false)
                            ->previewable(false),
                    ]),
                Section::make('Saluran alternatif WBS')
                    ->description('Tampil di halaman /wbs. Kosongkan saluran yang belum tersedia.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('saluran_surel')->label('Surel khusus WBS')->email()->maxLength(255),
                        TextInput::make('saluran_telepon')->label('Telepon/WhatsApp khusus WBS')->maxLength(50),
                        Textarea::make('saluran_surat')->label('Alamat surat')->rows(2)->maxLength(500)->columnSpanFull(),
                    ]),
                Section::make('Tanya Jawab (/faq)')
                    ->description('Target waktu penanganan ditampilkan otomatis dari Kategori dan SLA, tidak perlu ditulis di sini.')
                    ->schema([
                        Repeater::make('faq')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('pertanyaan')->label('Pertanyaan')->required()->maxLength(300),
                                Textarea::make('jawaban')->label('Jawaban')->required()->rows(3)->maxLength(3000),
                            ])
                            ->addActionLabel('Tambah pertanyaan')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['pertanyaan'] ?? null)
                            ->defaultItems(0),
                    ]),
                Section::make('Kebijakan Privasi (/privasi)')
                    ->description('Pisahkan paragraf dengan baris kosong. Baris yang diawali "- " tampil sebagai butir.')
                    ->schema([
                        Repeater::make('privasi')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('judul')->label('Judul bagian')->required()->maxLength(200),
                                Textarea::make('isi')->label('Isi')->required()->rows(5)->maxLength(5000),
                            ])
                            ->addActionLabel('Tambah bagian')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['judul'] ?? null)
                            ->defaultItems(0),
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

        try {
            $this->simpanIsian($user, $data);
        } catch (ValidationException $galat) {
            throw ValidationException::withMessages(['data.berkas_jaminan' => $galat->errors()[Pengaturan::WBS_BERKAS_JAMINAN] ?? $galat->getMessage()]);
        }

        $this->form->fill(self::kePetaFormulir(PengelolaKontenPublik::isian()));

        Notification::make()->success()->title('Konten publik tersimpan.')->send();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function simpanIsian(User $user, array $data): void
    {
        app(PengelolaKontenPublik::class)->simpan($user, [
            Pengaturan::WBS_BERKAS_JAMINAN => $data['berkas_jaminan'] ?? null,
            Pengaturan::WBS_SALURAN_SUREL => $data['saluran_surel'] ?? null,
            Pengaturan::WBS_SALURAN_TELEPON => $data['saluran_telepon'] ?? null,
            Pengaturan::WBS_SALURAN_SURAT => $data['saluran_surat'] ?? null,
            Pengaturan::PUBLIK_FAQ => $data['faq'] ?? [],
            Pengaturan::PUBLIK_PRIVASI => $data['privasi'] ?? [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $isian
     * @return array<string, mixed>
     */
    private static function kePetaFormulir(array $isian): array
    {
        return [
            'berkas_jaminan' => $isian[Pengaturan::WBS_BERKAS_JAMINAN],
            'saluran_surel' => $isian[Pengaturan::WBS_SALURAN_SUREL],
            'saluran_telepon' => $isian[Pengaturan::WBS_SALURAN_TELEPON],
            'saluran_surat' => $isian[Pengaturan::WBS_SALURAN_SURAT],
            'faq' => $isian[Pengaturan::PUBLIK_FAQ],
            'privasi' => $isian[Pengaturan::PUBLIK_PRIVASI],
        ];
    }
}
