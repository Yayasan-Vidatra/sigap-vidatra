<?php

namespace App\Filament\Pages;

use App\Enums\JenisLaporan;
use App\Models\Kategori;
use App\Models\User;
use App\Services\Rekap\EksporRekap;
use App\Services\Rekap\RekapLaporan;
use App\Services\WaktuLokal;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * H-18 Rekap dan Ekspor (US-23, UC-14, F-14): rekap pengaduan per periode, unit, dan kategori dengan rata-rata waktu
 * penyelesaian dan jumlah lewat batas waktu; untuk Pimpinan Yayasan dan Penerima Hasil juga jumlah WBS per kategori per
 * triwulan (< 3 disembunyikan). Angka dihitung RekapLaporan dari scope hak akses; ekspor lewat EksporRekap dan dicatat
 * di log audit.
 *
 * @property-read Schema $form
 */
class RekapEkspor extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Rekap dan Ekspor';

    protected static ?string $title = 'Rekap dan Ekspor';

    protected static ?string $slug = 'rekap';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.rekap-ekspor';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && RekapLaporan::bolehBukaRekap($user);
    }

    public function mount(): void
    {
        $hariIni = CarbonImmutable::now(WaktuLokal::zona());

        $this->form->fill([
            'dari' => $hariIni->startOfYear()->format('Y-m-d'),
            'sampai' => $hariIni->format('Y-m-d'),
            'tahun_wbs' => (string) $hariIni->year,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $user = $this->pengguna();
        $tahun = CarbonImmutable::now(WaktuLokal::zona())->year;

        return $schema
            ->columns(5)
            ->statePath('data')
            ->components([
                DatePicker::make('dari')->label('Dari tanggal')->native(false)->displayFormat('d M Y')->required()->live(),
                DatePicker::make('sampai')->label('Sampai tanggal')->native(false)->displayFormat('d M Y')->required()->afterOrEqual('dari')->live(),
                Select::make('unit_id')->label('Unit')->options(RekapLaporan::pilihanUnit($user))->placeholder('Semua')->live(),
                Select::make('kategori_id')
                    ->label('Kategori pengaduan')
                    ->options(fn (): array => Kategori::query()->where('jenis', JenisLaporan::Pengaduan->value)->orderBy('nama')->pluck('nama', 'id')->all())
                    ->placeholder('Semua')
                    ->live(),
                Select::make('tahun_wbs')
                    ->label('Tahun WBS')
                    ->options(collect(range($tahun, $tahun - 4))->mapWithKeys(fn (int $nilai): array => [(string) $nilai => (string) $nilai])->all())
                    ->selectablePlaceholder(false)
                    ->visible(RekapLaporan::bolehRekapWbs($user))
                    ->live(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('eksporExcel')
                ->label('Ekspor Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->action(fn (EksporRekap $ekspor): BinaryFileResponse => response()
                    ->download($ekspor->excel($this->pengguna(), $this->form->getState()), EksporRekap::namaBerkas('xlsx'))
                    ->deleteFileAfterSend()),
            Action::make('eksporPdf')
                ->label('Ekspor PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(function (EksporRekap $ekspor): StreamedResponse {
                    $isi = $ekspor->pdf($this->pengguna(), $this->form->getState());

                    return response()->streamDownload(function () use ($isi): void {
                        echo $isi;
                    }, EksporRekap::namaBerkas('pdf'), ['Content-Type' => 'application/pdf']);
                }),
        ];
    }

    /**
     * @return array{rekap: array<string, mixed>|null, minimal: int}
     */
    protected function getViewData(): array
    {
        try {
            $rekap = app(EksporRekap::class)->data($this->pengguna(), $this->data ?? []);
        } catch (ValidationException) {
            $rekap = null;
        }

        return ['rekap' => $rekap, 'minimal' => RekapLaporan::MINIMAL_WBS];
    }

    private function pengguna(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
