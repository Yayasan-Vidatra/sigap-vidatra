<?php

namespace App\Filament\Resources\Pengaduan\Tables;

use App\Enums\JenisLaporan;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Filament\Support\TabelPanel;
use App\Filament\Support\WarnaPanel;
use App\Models\Laporan;
use App\Models\User;
use App\Services\Rekap\RekapLaporan;
use App\Services\WaktuLokal;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar Kotak Masuk Pengaduan dengan filter status, unit, kategori, prioritas, lewat batas waktu, dan tanggal masuk,
 * serta pencarian kode lacak dan judul (F-07). Judul pengaduan tidak dienkripsi, sehingga bisa dicari. Semua filter
 * hanya mempersempit query PengaduanResource::getEloquentQuery() (scope kotakMasukPengaduan).
 */
class PengaduanTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('kode_lacak')
                    ->label('Kode lacak')
                    ->searchable()
                    ->width('158px'),
                TextColumn::make('judul')
                    ->label('Judul')
                    ->searchable()
                    ->lineClamp(2)
                    ->wrap(),
                TextColumn::make('unit.nama')
                    ->label('Unit')
                    ->wrap()
                    ->width('120px'),
                TextColumn::make('kategori.nama')
                    ->label('Kategori')
                    ->placeholder('Belum ada')
                    ->wrap()
                    ->width('156px'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->wrap()
                    ->color(fn (StatusLaporan $state): string => self::warnaStatus($state))
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
                    ->color(fn (Laporan $record): ?string => self::lewatBatas($record) ? 'danger' : null)
                    ->weight(fn (Laporan $record): ?FontWeight => self::lewatBatas($record) ? FontWeight::Bold : null)
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
            ->defaultSort('dibuat_pada', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::pilihanStatus())
                    // Dipilih lewat tombol status di atas tabel (ListPengaduan); kotak pilihnya disembunyikan.
                    ->modifyFormFieldUsing(fn (Select $field): Select => $field->extraFieldWrapperAttributes(['class' => 'sigap-filter-tersembunyi'])),
                SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'nama', fn (Builder $query) => $query->untukPengaduan())
                    ->hidden(fn (): bool => self::penggunaAdminSekolah()),
                SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama', fn (Builder $query) => $query->where('jenis', JenisLaporan::Pengaduan->value)),
                SelectFilter::make('prioritas')
                    ->label('Prioritas')
                    ->options(Prioritas::class),
                Filter::make('lewat_batas')
                    ->label('Lewat batas waktu')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => self::lewatBatasQuery($query)),
                Filter::make('dibuat_pada')
                    ->label('Tanggal masuk')
                    ->schema([
                        DatePicker::make('dari')->label('Masuk dari tanggal'),
                        DatePicker::make('sampai')->label('Masuk sampai tanggal'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $dari) => $q->where('dibuat_pada', '>=', WaktuLokal::awalHari($dari)))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $sampai) => $q->where('dibuat_pada', '<=', WaktuLokal::akhirHari($sampai)))),
            ])
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 4, 'xl' => 6])
            ->recordActions([])
            ->toolbarActions([]);

        return TabelPanel::terapkan($table, 'pengaduan', 'pengaduan');
    }

    /**
     * Status pengaduan berurutan seperti alur Bagian 6; dipakai filter dan tombol status Kotak Masuk.
     *
     * @return array<string, string>
     */
    public static function pilihanStatus(): array
    {
        return collect(StatusLaporan::untuk(JenisLaporan::Pengaduan))
            ->mapWithKeys(fn (StatusLaporan $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    /**
     * Pengaduan yang masih berjalan (Diproses atau Menunggu konfirmasi) dan sudah melewati batas waktu, sama dengan
     * kartu "Lewat batas waktu" di Dasbor (RekapLaporan::ringkasan).
     *
     * @param  Builder<Laporan>  $query
     * @return Builder<Laporan>
     */
    public static function lewatBatasQuery(Builder $query): Builder
    {
        return $query
            ->whereIn('status', array_map(fn (StatusLaporan $status): string => $status->value, RekapLaporan::STATUS_PENGADUAN_BERJALAN))
            ->where('batas_waktu', '<', now());
    }

    public static function warnaStatus(StatusLaporan $status): string
    {
        return WarnaPanel::status($status);
    }

    public static function warnaPrioritas(Prioritas $prioritas): string
    {
        return match ($prioritas) {
            Prioritas::Tinggi => 'warning',
            Prioritas::Sedang => 'info',
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
