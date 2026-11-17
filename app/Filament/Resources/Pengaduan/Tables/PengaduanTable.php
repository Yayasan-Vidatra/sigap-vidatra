<?php

namespace App\Filament\Resources\Pengaduan\Tables;

use App\Enums\JenisLaporan;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Models\Laporan;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar Kotak Masuk Pengaduan dengan filter unit, kategori, status, prioritas, dan tanggal masuk, serta
 * pencarian kode lacak dan judul (F-07). Judul pengaduan tidak dienkripsi, sehingga bisa dicari.
 */
class PengaduanTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_lacak')
                    ->label('Kode lacak')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('judul')
                    ->label('Judul')
                    ->searchable()
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('unit.nama')
                    ->label('Unit'),
                TextColumn::make('kategori.nama')
                    ->label('Kategori')
                    ->placeholder('Belum ada'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (StatusLaporan $state): string => self::warnaStatus($state)),
                TextColumn::make('prioritas')
                    ->label('Prioritas')
                    ->badge()
                    ->color(fn (Prioritas $state): string => self::warnaPrioritas($state))
                    ->placeholder('-'),
                TextColumn::make('batas_waktu')
                    ->label('Batas waktu')
                    ->date('d M Y')
                    ->sortable()
                    ->placeholder('-')
                    ->color(fn (Laporan $record): ?string => self::lewatBatas($record) ? 'danger' : null),
                TextColumn::make('dibuat_pada')
                    ->label('Masuk')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('dibuat_pada', 'desc')
            ->filters([
                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'nama')
                    ->hidden(fn (): bool => self::penggunaAdminSekolah()),
                SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama', fn (Builder $query) => $query->where('jenis', JenisLaporan::Pengaduan->value)),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(StatusLaporan::untuk(JenisLaporan::Pengaduan))
                        ->mapWithKeys(fn (StatusLaporan $status): array => [$status->value => $status->getLabel()])
                        ->all()),
                SelectFilter::make('prioritas')
                    ->label('Prioritas')
                    ->options(Prioritas::class),
                Filter::make('dibuat_pada')
                    ->label('Tanggal masuk')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $dari) => $q->whereDate('dibuat_pada', '>=', $dari))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $sampai) => $q->whereDate('dibuat_pada', '<=', $sampai))),
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
            StatusLaporan::Terverifikasi => 'info',
            StatusLaporan::Diproses, StatusLaporan::MenungguKonfirmasi => 'primary',
            StatusLaporan::Selesai => 'success',
            StatusLaporan::Ditolak => 'danger',
            default => 'gray',
        };
    }

    public static function warnaPrioritas(Prioritas $prioritas): string
    {
        return match ($prioritas) {
            Prioritas::Tinggi => 'danger',
            Prioritas::Sedang => 'warning',
            Prioritas::Rendah => 'gray',
        };
    }

    /**
     * Pengaduan yang masih ditangani unit dan sudah melewati batas waktu SLA.
     */
    public static function lewatBatas(Laporan $laporan): bool
    {
        return $laporan->status === StatusLaporan::Diproses && ($laporan->batas_waktu?->isPast() ?? false);
    }

    private static function penggunaAdminSekolah(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdminSekolah();
    }
}
