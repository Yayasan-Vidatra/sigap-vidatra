<?php

namespace App\Filament\Resources\LaporanWbs\Tables;

use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Models\Laporan;
use App\Services\WaktuLokal;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar Ruang Kerja WBS (H-13). Judul dan isi WBS terenkripsi, sehingga pencarian hanya lewat kode lacak.
 */
class LaporanWbsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_lacak')->label('Kode lacak')->searchable()->copyable(),
                TextColumn::make('kategori.nama')->label('Jenis pelanggaran')->wrap(),
                TextColumn::make('unit.nama')->label('Unit')->placeholder('-'),
                TextColumn::make('kedudukan_terlapor')->label('Kedudukan terlapor')->badge()->placeholder('Tidak tahu'),
                TextColumn::make('sumber')->label('Sumber'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (StatusLaporan $state): string => self::warnaStatus($state)),
                TextColumn::make('investigator.name')->label('Investigator')->placeholder('-'),
                TextColumn::make('batas_waktu')
                    ->label('Batas waktu')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('-')
                    ->color(fn (Laporan $record): ?string => self::lewatBatas($record) ? 'danger' : null),
                TextColumn::make('dibuat_pada')->label('Masuk')->dateTime('d M Y H:i')->suffix(' '.WaktuLokal::label())->sortable(),
            ])
            ->defaultSort('dibuat_pada', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(StatusLaporan::untuk(JenisLaporan::Wbs))
                        ->mapWithKeys(fn (StatusLaporan $status): array => [$status->value => $status->getLabel()])
                        ->all()),
                SelectFilter::make('kategori_id')
                    ->label('Jenis pelanggaran')
                    ->relationship('kategori', 'nama', fn (Builder $query) => $query->where('jenis', JenisLaporan::Wbs->value)),
                SelectFilter::make('kedudukan_terlapor')->label('Kedudukan terlapor')->options(KedudukanTerlapor::class),
                SelectFilter::make('sumber')->label('Sumber')->options(SumberLaporan::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Buka'),
            ])
            ->toolbarActions([]);
    }

    public static function warnaStatus(StatusLaporan $status): string
    {
        return match ($status) {
            StatusLaporan::Diterima => 'warning',
            StatusLaporan::Ditelaah => 'info',
            StatusLaporan::Diinvestigasi, StatusLaporan::DiinvestigasiDiperpanjang => 'primary',
            StatusLaporan::Selesai => 'success',
            StatusLaporan::TidakDitindaklanjuti => 'danger',
            default => 'gray',
        };
    }

    /**
     * Telaah (5 hari kerja) atau investigasi (30 hari kerja) yang sudah melewati batasnya.
     */
    public static function lewatBatas(Laporan $laporan): bool
    {
        return in_array($laporan->status, [StatusLaporan::Diterima, StatusLaporan::Ditelaah, StatusLaporan::Diinvestigasi, StatusLaporan::DiinvestigasiDiperpanjang], true)
            && ($laporan->batas_waktu?->isPast() ?? false);
    }
}
