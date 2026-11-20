<?php

namespace App\Filament\Resources\LogAudits\Tables;

use App\Enums\AksiAudit;
use App\Filament\Support\TabelPanel;
use App\Models\LogAudit;
use App\Models\RiwayatPengaturan;
use App\Services\WaktuLokal;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Tabel log audit tanpa aksi ubah atau hapus (NF-07). Baris `mengubah_pengaturan` menampilkan nilai lama dan baru
 * dari riwayat_pengaturan (H-19). Ekspor menyusul bersama Rekap dan Ekspor (H-18).
 */
class LogAuditsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('waktu')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')->suffix(' '.WaktuLokal::label())
                    ->sortable()
                    ->wrap()
                    ->width('128px'),
                TextColumn::make('user.name')
                    ->label('Pengguna')
                    ->placeholder('Sistem / tidak dikenal')
                    ->searchable()
                    ->wrap()
                    ->width('170px'),
                TextColumn::make('aksi')
                    ->label('Aksi')
                    ->formatStateUsing(fn (string $state): string => AksiAudit::tryFrom($state)?->getLabel() ?? $state)
                    ->wrap()
                    ->width('190px'),
                TextColumn::make('objek')
                    ->label('Objek')
                    ->searchable()
                    ->wrap()
                    ->width('200px'),
                TextColumn::make('rincian')
                    ->label('Nilai lama → baru')
                    ->state(fn (LogAudit $record): array => $record->riwayatPengaturan
                        ->map(fn (RiwayatPengaturan $riwayat): string => $riwayat->kolom.': '
                            .Str::limit($riwayat->nilai_lama ?? '(kosong)', 80).' → '
                            .Str::limit($riwayat->nilai_baru ?? '(kosong)', 80))
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->defaultSort('waktu', 'desc')
            ->filters([
                SelectFilter::make('aksi')
                    ->label('Aksi')
                    ->options(AksiAudit::class),
                SelectFilter::make('user_id')
                    ->label('Pengguna')
                    ->relationship('user', 'name')
                    ->searchable(),
                Filter::make('waktu')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $dari) => $q->where('waktu', '>=', WaktuLokal::awalHari($dari)))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $sampai) => $q->where('waktu', '<=', WaktuLokal::akhirHari($sampai)))),
            ])
            ->recordActions([])
            ->toolbarActions([]);

        return TabelPanel::terapkan($table, 'ringkas', 'log audit');
    }
}
