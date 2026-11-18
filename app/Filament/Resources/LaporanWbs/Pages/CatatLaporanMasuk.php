<?php

namespace App\Filament\Resources\LaporanWbs\Pages;

use App\Enums\JenisBerkas;
use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\SumberLaporan;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Filament\Resources\LaporanWbs\LaporanWbsResource;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Lampiran\BerkasDitolak;
use App\Services\WaktuLokal;
use App\Services\Wbs\AksesWbs;
use App\Services\Wbs\PembuatLaporanWbs;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * H-14 Catat Laporan Masuk (US-18, UC-18, F-17): Ketua Tim WBS mencatat laporan dari surel, telepon/WhatsApp, atau
 * surat. Isi disalin apa adanya; tangkapan layar atau pindaian diunggah sebagai lampiran WBS (terenkripsi, metadata
 * dihapus). Setelah tersimpan, kode lacak dan PIN sementara ditampilkan sekali di halaman ini.
 */
class CatatLaporanMasuk extends CreateRecord
{
    protected static string $resource = LaporanWbsResource::class;

    protected string $view = 'filament.wbs.catat-laporan-masuk';

    protected static ?string $title = 'Catat laporan masuk';

    protected static bool $canCreateAnother = false;

    /**
     * Kode lacak dan PIN sementara hasil pencatatan, hanya di state halaman ini.
     *
     * @var array{id: int, kode_lacak: string, pin: string, tertutup: bool, penelaah: string}|null
     */
    public ?array $hasil = null;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::forUser(Filament::auth()->user())->allows('catatLaporanMasuk', Laporan::class);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Saluran dan pelapor')
                    ->columns(2)
                    ->schema([
                        Select::make('sumber')
                            ->label('Sumber laporan')
                            ->options(collect(SumberLaporan::cases())
                                ->reject(fn (SumberLaporan $sumber): bool => $sumber === SumberLaporan::Laman)
                                ->mapWithKeys(fn (SumberLaporan $sumber): array => [$sumber->value => $sumber->getLabel()])
                                ->all())
                            ->required(),
                        Radio::make('mode')
                            ->label('Pelapor')
                            ->options(['anonim' => 'Anonim', 'bernama' => 'Bernama'])
                            ->default('anonim')
                            ->required()
                            ->live(),
                        TextInput::make('nama')->label('Nama pelapor')->maxLength(150)->required()->visible(fn (Get $get): bool => $get('mode') === 'bernama'),
                        TextInput::make('email')->label('Surel pelapor')->email()->maxLength(150)->visible(fn (Get $get): bool => $get('mode') === 'bernama'),
                        TextInput::make('no_hp')->label('Nomor HP/WhatsApp pelapor')->maxLength(20)->visible(fn (Get $get): bool => $get('mode') === 'bernama'),
                    ]),
                Section::make('Pelanggaran dan terlapor')
                    ->columns(2)
                    ->schema([
                        Select::make('kategori_id')
                            ->label('Jenis pelanggaran')
                            ->options(fn (): array => Kategori::query()->where('jenis', JenisLaporan::Wbs->value)->orderBy('id')->pluck('nama', 'id')->all())
                            ->required(),
                        Select::make('unit_id')
                            ->label('Unit')
                            ->options(fn (): array => Unit::query()->where('aktif', true)->orderBy('id')->pluck('nama', 'id')->all())
                            ->required(),
                        Repeater::make('terlapor')
                            ->label('Pihak terlapor')
                            ->schema([
                                TextInput::make('nama')->label('Nama')->required()->maxLength(150),
                                TextInput::make('jabatan')->label('Jabatan')->maxLength(150),
                            ])
                            ->columns(2)
                            ->minItems(1)
                            ->maxItems((int) config('sigap.wbs.maks_terlapor'))
                            ->defaultItems(1)
                            ->addActionLabel('Tambah terlapor')
                            ->columnSpanFull(),
                        Select::make('kedudukan_terlapor')
                            ->label('Kedudukan terlapor')
                            ->options(KedudukanTerlapor::class)
                            ->helperText('Bila terlapor lebih dari satu, pilih kedudukan tertinggi.')
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('Isi laporan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('judul')->label('Judul singkat')->required()->maxLength(200)->columnSpanFull(),
                        Textarea::make('kronologi')
                            ->label('Isi laporan')
                            ->helperText('Salin isi surel, pesan, atau surat apa adanya.')
                            ->required()
                            ->minLength(20)
                            ->maxLength(5000)
                            ->rows(8)
                            ->columnSpanFull(),
                        DateTimePicker::make('waktu_kejadian')->label('Waktu kejadian')->seconds(false)->maxDate(now()),
                        TextInput::make('lokasi')->label('Lokasi')->maxLength(200),
                        Textarea::make('saksi')->label('Pihak lain yang mengetahui')->maxLength(1000)->rows(2)->columnSpanFull(),
                        FileUpload::make('lampiran')
                            ->label('Tangkapan layar, surel, atau pindaian surat')
                            ->helperText('Paling banyak '.config('sigap.lampiran.maks_berkas').' berkas, masing-masing 10 MB. Disimpan terenkripsi; metadata dihapus otomatis.')
                            ->multiple()
                            ->maxFiles((int) config('sigap.lampiran.maks_berkas'))
                            ->maxSize(10 * 1024)
                            ->acceptedFileTypes(self::mimeWbs())
                            ->storeFiles(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        $berkas = array_map(fn (TemporaryUploadedFile $file): array => [
            'path' => $file->getRealPath(),
            'nama_asli' => $file->getClientOriginalName(),
        ], array_values($data['lampiran'] ?? []));

        try {
            ['laporan' => $laporan, 'pin' => $pin] = app(PembuatLaporanWbs::class)->catatLaporanMasuk(
                $user,
                [
                    ...$data,
                    'terlapor' => array_values($data['terlapor'] ?? []),
                    // DateTimePicker mengembalikan waktu UTC; PembuatLaporanWbs menerima masukan WITA seperti formulir publik.
                    'waktu_kejadian' => filled($data['waktu_kejadian'] ?? null)
                        ? WaktuLokal::keLokal(CarbonImmutable::parse($data['waktu_kejadian'], (string) config('app.timezone')))->format('Y-m-d H:i:s')
                        : null,
                ],
                SumberLaporan::from($data['sumber'] instanceof SumberLaporan ? $data['sumber']->value : $data['sumber']),
                $berkas,
            );
        } catch (BerkasDitolak|AksiPengaduanTidakSah $galat) {
            Notification::make()->danger()->title($galat->getMessage())->send();
            $this->halt();
        }

        $tertutup = ! Gate::forUser($user)->allows('bukaLaporanWbs', $laporan);

        $this->hasil = [
            'id' => $laporan->getKey(),
            'kode_lacak' => $laporan->kode_lacak,
            'pin' => $pin,
            'tertutup' => $tertutup,
            'penelaah' => collect(AksesWbs::kedudukanEfektif($laporan)->peranPenelaahUtama())->map->getLabel()->implode(' / '),
        ];

        return $laporan;
    }

    /**
     * Hasil ditampilkan di halaman ini, bukan lewat pengalihan dan notifikasi sesi, agar PIN tidak tersimpan di sesi.
     */
    protected function afterCreate(): void
    {
        $this->form->fill();
        $this->halt();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    /**
     * @return list<string>
     */
    private static function mimeWbs(): array
    {
        return array_values(array_merge(...array_map(
            fn (JenisBerkas $jenis): array => $jenis->daftarMime(),
            array_filter(JenisBerkas::cases(), fn (JenisBerkas $jenis): bool => $jenis->bolehUntuk(JenisLaporan::Wbs)),
        )));
    }
}
