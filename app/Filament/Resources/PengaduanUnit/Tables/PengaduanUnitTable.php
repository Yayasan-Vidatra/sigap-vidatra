<?php

namespace App\Filament\Resources\PengaduanUnit\Tables;

use App\Enums\JenisLaporan;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Filament\Resources\Pengaduan\Tables\PengaduanTable;
use App\Models\Laporan;
use App\Services\WaktuLokal;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar Pengaduan Unit Saya. Batas waktu yang sudah lewat ditandai merah; urutan bawaan dari batas waktu
 * terdekat agar PIC melihat yang paling mendesak lebih dulu.
 */
class PengaduanUnitTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_lacak')->label('Kode lacak')->searchable(),
                TextColumn::make('judul')->label('Judul')->searchable()->limit(60)->wrap(),
                TextColumn::make('kategori.nama')->label('Kategori'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (StatusLaporan $state): string => PengaduanTable::warnaStatus($state)),
                TextColumn::make('prioritas')
                    ->label('Prioritas')
                    ->badge()
                    ->color(fn (Prioritas $state): string => PengaduanTable::warnaPrioritas($state))
                    ->placeholder('-'),
                TextColumn::make('batas_waktu')
                    ->label('Batas waktu')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('-')
                    ->color(fn (Laporan $record): ?string => PengaduanTable::lewatBatas($record) ? 'danger' : null),
                TextColumn::make('dibuat_pada')
                    ->label('Masuk')
                    ->dateTime('d M Y H:i')
                    ->suffix(' '.WaktuLokal::label())
                    ->sortable(),
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
            ])
            ->recordActions([
                ViewAction::make()->label('Buka'),
            ])
            ->toolbarActions([]);
    }
}
