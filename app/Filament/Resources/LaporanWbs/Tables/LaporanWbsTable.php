<?php

namespace App\Filament\Resources\LaporanWbs\Tables;

use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Filament\Support\LabelRingkas;
use App\Filament\Support\TabelPanel;
use App\Filament\Support\WarnaPanel;
use App\Models\Laporan;
use App\Services\Rekap\RekapLaporan;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar Ruang Kerja WBS (H-13). Judul dan isi WBS terenkripsi, sehingga pencarian hanya lewat kode lacak. Semua
 * filter hanya mempersempit query LaporanWbsResource::getEloquentQuery() (scope wbsTerbukaBagi, matriks Bagian 3.1).
 */
class LaporanWbsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('kode_lacak')->label('Kode lacak')->searchable()->width('156px'),
                TextColumn::make('kategori.nama')->label('Jenis pelanggaran')->lineClamp(2)->wrap(),
                TextColumn::make('unit.nama')->label('Unit')->placeholder('–')->wrap()->width('112px'),
                TextColumn::make('kedudukan_terlapor')
                    ->label('Kedudukan terlapor')
                    ->formatStateUsing(fn (?KedudukanTerlapor $state): string => LabelRingkas::kedudukan($state))
                    ->placeholder(LabelRingkas::kedudukan(null))
                    ->wrap()
                    ->wrapHeader()
                    ->width('124px'),
                TextColumn::make('sumber')
                    ->label('Sumber')
                    ->formatStateUsing(fn (?SumberLaporan $state): string => LabelRingkas::sumber($state))
                    ->wrap()
                    ->width('104px'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->wrap()
                    ->color(fn (StatusLaporan $state): string => self::warnaStatus($state))
                    ->width('140px'),
                TextColumn::make('investigator.name')->label('Investigator')->placeholder('–')->wrap()->width('124px'),
                TextColumn::make('batas_waktu')
                    ->label('Batas waktu')
                    ->date('j M Y')
                    ->sortable()
                    ->placeholder('–')
                    ->wrap()
                    ->color(fn (Laporan $record): ?string => self::lewatBatas($record) ? 'danger' : null)
                    ->weight(fn (Laporan $record): ?FontWeight => self::lewatBatas($record) ? FontWeight::Bold : null)
                    ->width('104px'),
                TextColumn::make('dibuat_pada')->label('Masuk')->date('j M Y')->sortable()->wrap()->width('84px'),
                TabelPanel::kolomPanah()->width('32px'),
            ])
            ->defaultSort('dibuat_pada', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(StatusLaporan::untuk(JenisLaporan::Wbs))
                        ->mapWithKeys(fn (StatusLaporan $status): array => [$status->value => $status->getLabel()])
                        ->all())
                    // Dipilih lewat tombol status di atas tabel (ListLaporanWbs); kotak pilihnya disembunyikan.
                    ->modifyFormFieldUsing(fn (Select $field): Select => $field->extraFieldWrapperAttributes(['class' => 'sigap-filter-tersembunyi'])),
                SelectFilter::make('kategori_id')
                    ->label('Jenis pelanggaran')
                    ->relationship('kategori', 'nama', fn (Builder $query) => $query->where('jenis', JenisLaporan::Wbs->value)),
                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'nama'),
                SelectFilter::make('kedudukan_terlapor')->label('Kedudukan terlapor')->options(LabelRingkas::pilihanKedudukan()),
                SelectFilter::make('sumber')->label('Sumber')->options(LabelRingkas::pilihanSumber()),
                Filter::make('sedang_ditangani')
                    ->label('Sedang ditangani')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', self::nilaiStatus(array_slice(RekapLaporan::STATUS_WBS_BERJALAN, 1)))),
                Filter::make('lewat_batas')
                    ->label('Lewat batas waktu')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', self::nilaiStatus(RekapLaporan::STATUS_WBS_BERJALAN))
                        ->where('batas_waktu', '<', now())),
            ])
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 4, 'xl' => 6])
            ->recordActions([])
            ->toolbarActions([]);

        return TabelPanel::terapkan($table, 'wbs', 'laporan');
    }

    public static function warnaStatus(StatusLaporan $status): string
    {
        return WarnaPanel::status($status);
    }

    /**
     * Telaah (5 hari kerja) atau investigasi (30 hari kerja) yang sudah melewati batasnya.
     */
    public static function lewatBatas(Laporan $laporan): bool
    {
        return in_array($laporan->status, [StatusLaporan::Diterima, StatusLaporan::Ditelaah, StatusLaporan::Diinvestigasi, StatusLaporan::DiinvestigasiDiperpanjang], true)
            && ($laporan->batas_waktu?->isPast() ?? false);
    }

    /**
     * @param  list<StatusLaporan>  $status
     * @return list<string>
     */
    private static function nilaiStatus(array $status): array
    {
        return array_map(fn (StatusLaporan $item): string => $item->value, $status);
    }
}
