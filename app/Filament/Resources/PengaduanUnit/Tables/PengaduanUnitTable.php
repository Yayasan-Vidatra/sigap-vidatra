<?php

namespace App\Filament\Resources\PengaduanUnit\Tables;

use App\Enums\JenisLaporan;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Filament\Resources\Pengaduan\Tables\PengaduanTable;
use App\Filament\Support\TabelPanel;
use App\Models\Laporan;
use App\Services\WaktuLokal;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar Pengaduan Unit Saya. Batas waktu yang sudah lewat ditandai merah; urutan bawaan dari batas waktu
 * terdekat agar PIC melihat yang paling mendesak lebih dulu. Semua filter hanya mempersempit query
 * PengaduanUnitResource::getEloquentQuery() (scope pengaduanUnitPic).
 */
class PengaduanUnitTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('kode_lacak')->label('Kode lacak')->searchable()->width('158px'),
                TextColumn::make('judul')->label('Judul')->searchable()->lineClamp(2)->wrap(),
                TextColumn::make('kategori.nama')->label('Kategori')->wrap()->width('176px'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->wrap()
                    ->color(fn (StatusLaporan $state): string => PengaduanTable::warnaStatus($state))
                    ->width('176px'),
                TextColumn::make('prioritas')
                    ->label('Prioritas')
                    ->placeholder('–')
                    ->width('92px'),
                TextColumn::make('batas_waktu')
                    ->label('Batas waktu')
                    ->date('j M Y')
                    ->sortable()
                    ->placeholder('–')
                    ->wrap()
                    ->color(fn (Laporan $record): ?string => PengaduanTable::lewatBatas($record) ? 'danger' : null)
                    ->weight(fn (Laporan $record): ?FontWeight => PengaduanTable::lewatBatas($record) ? FontWeight::Bold : null)
                    ->width('116px'),
                TextColumn::make('dibuat_pada')
                    ->label('Masuk')
                    ->dateTime('j M Y H:i')
                    ->suffix(' '.WaktuLokal::label())
                    ->sortable()
                    ->wrap()
                    ->width('112px'),
                TabelPanel::kolomPanah(),
            ])
            ->defaultSort('batas_waktu')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect([StatusLaporan::Diproses, StatusLaporan::MenungguKonfirmasi, StatusLaporan::Selesai])
                        ->mapWithKeys(fn (StatusLaporan $status): array => [$status->value => $status->getLabel()])
                        ->all()),
                SelectFilter::make('prioritas')->label('Prioritas')->options(Prioritas::class),
                SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama', fn (Builder $query) => $query->where('jenis', JenisLaporan::Pengaduan->value)),
                Filter::make('belum_ditindaklanjuti')
                    ->label('Tindak lanjut belum diisi')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', StatusLaporan::Diproses->value)
                        ->whereDoesntHave('disposisi', fn (Builder $disposisi) => $disposisi->whereNotNull('tindak_lanjut'))),
                Filter::make('lewat_batas')
                    ->label('Lewat batas waktu')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => PengaduanTable::lewatBatasQuery($query)),
            ])
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 5])
            ->recordActions([])
            ->toolbarActions([]);

        return TabelPanel::terapkan($table, 'pengaduan', 'pengaduan');
    }
}
