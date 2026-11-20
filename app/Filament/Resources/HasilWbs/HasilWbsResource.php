<?php

namespace App\Filament\Resources\HasilWbs;

use App\Enums\StatusLaporan;
use App\Filament\Resources\HasilWbs\Pages\ListHasilWbs;
use App\Filament\Resources\HasilWbs\Pages\ViewHasilWbs;
use App\Filament\Resources\LaporanWbs\Tables\LaporanWbsTable;
use App\Filament\Support\TabelPanel;
use App\Models\Laporan;
use App\Models\RiwayatStatus;
use App\Models\User;
use App\Services\WaktuLokal;
use App\Services\Wbs\AksesWbs;
use App\Services\Wbs\PenangananWbs;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * H-16 Hasil WBS untuk Saya (US-21, UC-13 langkah 5 dan 1a; F-19). Penerima hasil menurut matriks melihat ringkasan,
 * kesimpulan, dan rekomendasi laporan yang ditujukan kepadanya, menyetujui atau menolak perpanjangan investigasi, dan
 * menandai hasil yang sudah ditindaklanjuti. Sengaja tanpa identitas pelapor, kode lacak, kronologi, saksi, lampiran,
 * dan pesan. Lingkup data ditegakkan di query (AksesWbs::idHasilUntuk) dan di LaporanPolicy (bukaHasilWbs).
 */
class HasilWbsResource extends Resource
{
    protected static ?string $model = Laporan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Hasil WBS untuk Saya';

    protected static ?string $modelLabel = 'hasil WBS';

    protected static ?string $pluralModelLabel = 'hasil WBS';

    protected static ?string $slug = 'hasil-wbs';

    protected static bool $isGloballySearchable = false;

    /**
     * @return Builder<Laporan>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        return parent::getEloquentQuery()
            ->with(['unit', 'kategori', 'terlapor'])
            ->when(
                $user instanceof User,
                fn (Builder $query) => $query->whereIn('id', AksesWbs::idHasilUntuk($user)),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            );
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Laporan ? "Laporan WBS #{$record->getKey()}" : 'Hasil WBS';
    }

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return Gate::forUser(Filament::auth()->user())->inspect('lihatHasilWbs', Laporan::class);
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return Gate::forUser(Filament::auth()->user())->inspect('bukaHasilWbs', $record);
    }

    public static function table(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('id')->label('No.')->formatStateUsing(fn (int $state): string => "#{$state}")->width('80px'),
                TextColumn::make('kategori.nama')->label('Jenis pelanggaran')->lineClamp(2)->wrap(),
                TextColumn::make('unit.nama')->label('Unit')->placeholder('–')->wrap()->width('140px'),
                TextColumn::make('status')->label('Status')->badge()->wrap()->color(fn (StatusLaporan $state): string => LaporanWbsTable::warnaStatus($state))->width('160px'),
                TextColumn::make('keperluan')
                    ->label('Perlu tindakan')
                    ->state(fn (Laporan $record): ?string => match (true) {
                        PenangananWbs::bisa('putuskanPerpanjangan', $record) => 'Persetujuan perpanjangan',
                        PenangananWbs::bisa('tandaiDitindaklanjuti', $record) => 'Tindak lanjut hasil',
                        default => null,
                    })
                    ->badge()
                    ->wrap()
                    ->color('status-diproses')
                    ->placeholder('–')
                    ->width('190px'),
                TextColumn::make('hasil_ditindaklanjuti_pada')->label('Ditindaklanjuti')->date('j M Y')->placeholder('–')->wrap()->width('156px'),
                TabelPanel::kolomPanah(),
            ])
            ->defaultSort('diperbarui_pada', 'desc')
            ->filters([
                // Sama dengan kartu "Hasil WBS menunggu tindak lanjut" di Dasbor; hanya mempersempit idHasilUntuk.
                Filter::make('belum_ditindaklanjuti')
                    ->label('Menunggu tindak lanjut')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('kesimpulan')->whereNull('hasil_ditindaklanjuti_pada')),
            ])
            ->recordActions([])
            ->toolbarActions([]);

        return TabelPanel::terapkan($table, 'ringkas', 'hasil WBS');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Ringkasan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('judul')->label('Judul')->columnSpanFull(),
                        TextEntry::make('kategori.nama')->label('Jenis pelanggaran'),
                        TextEntry::make('unit.nama')->label('Unit')->placeholder('-'),
                        TextEntry::make('status')->label('Status')->badge()->color(fn (StatusLaporan $state): string => LaporanWbsTable::warnaStatus($state)),
                        TextEntry::make('kedudukan_efektif')->label('Kedudukan terlapor')->state(fn (Laporan $record): string => AksesWbs::kedudukanEfektif($record)->getLabel()),
                        TextEntry::make('penerima')->label('Penerima hasil')->state(fn (Laporan $record): string => AksesWbs::kedudukanEfektif($record)->penerimaHasil()),
                        TextEntry::make('batas_waktu')->label('Batas investigasi')->date('j M Y')->placeholder('-'),
                    ]),
                Section::make('Pihak terlapor')
                    ->schema([
                        RepeatableEntry::make('terlapor')
                            ->hiddenLabel()
                            ->columns(2)
                            ->schema([
                                TextEntry::make('nama')->label('Nama'),
                                TextEntry::make('jabatan')->label('Jabatan')->placeholder('-'),
                            ]),
                    ]),
                Section::make('Permintaan perpanjangan')
                    ->visible(fn (Laporan $record): bool => $record->perpanjangan_diajukan_pada !== null)
                    ->schema([
                        TextEntry::make('perpanjangan_diajukan_pada')->label('Diajukan')->dateTime('j M Y H:i')->suffix(' '.WaktuLokal::label()),
                        TextEntry::make('alasan_perpanjangan')
                            ->label('Alasan dari Tim WBS')
                            ->state(fn (Laporan $record): ?string => self::alasanPerpanjangan($record)),
                    ]),
                Section::make('Kesimpulan dan rekomendasi')
                    ->visible(fn (Laporan $record): bool => $record->kesimpulan !== null)
                    ->schema([
                        TextEntry::make('kesimpulan')->label('Kesimpulan')->badge(),
                        TextEntry::make('rekomendasi')->label('Rekomendasi')->prose(),
                        TextEntry::make('hasil_ditindaklanjuti_pada')->label('Ditindaklanjuti')->dateTime('j M Y H:i')->suffix(' '.WaktuLokal::label())->placeholder('Belum'),
                    ]),
            ]);
    }

    /**
     * Alasan pengajuan perpanjangan terakhir dari riwayat (catatan petugas yang memang ditujukan ke penerima hasil).
     */
    private static function alasanPerpanjangan(Laporan $laporan): ?string
    {
        $catatan = $laporan->riwayatStatus()
            ->latest('id')
            ->get()
            ->first(fn (RiwayatStatus $riwayat): bool => str_starts_with((string) $riwayat->catatan, PenangananWbs::AWALAN_PERPANJANGAN))
            ?->catatan;

        return $catatan === null ? null : trim(substr($catatan, strlen(PenangananWbs::AWALAN_PERPANJANGAN)));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHasilWbs::route('/'),
            'view' => ViewHasilWbs::route('/{record}'),
        ];
    }
}
