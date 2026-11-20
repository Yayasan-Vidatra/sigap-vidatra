<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

/**
 * Pengaturan tampilan bersama tabel daftar panel (prototipe prd/mockup/panel-*.html): lebar kolom tetap, kartu di
 * layar sempit (kelas sigap-tabel di resources/css/filament/panel/theme.css), filter di atas tabel yang langsung
 * berlaku, pagination 10/25/50, dan pesan kosong dengan tombol "Hapus semua filter". Hanya tampilan; query dan
 * hak akses tetap milik resource masing-masing.
 */
class TabelPanel
{
    public const PILIHAN_PER_HALAMAN = [10, 25, 50];

    /**
     * @param  string  $varian  Nama tata letak kartu, misalnya "pengaduan" atau "wbs".
     * @param  string  $benda  Kata benda untuk pesan kosong, misalnya "pengaduan" atau "laporan".
     */
    public static function terapkan(Table $table, string $varian, string $benda): Table
    {
        return $table
            ->extraAttributes(['class' => "sigap-tabel sigap-tabel-{$varian}"])
            ->paginationPageOptions(self::PILIHAN_PER_HALAMAN)
            ->defaultPaginationPageOption(10)
            ->deferFilters(false)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersResetAction(fn (Action $action): Action => $action->color('gray'))
            ->filtersRemoveAllAction(fn (Action $action): Action => $action
                ->link()
                ->icon(null)
                ->tooltip(null)
                ->label('Hapus semua filter')
                ->color('primary'))
            ->emptyStateIcon(Heroicon::OutlinedInbox)
            ->emptyStateHeading(fn (HasTable $livewire): string => self::adaFilterAktif($livewire)
                ? "Tidak ada {$benda} yang cocok dengan filter."
                : "Belum ada {$benda}.")
            ->emptyStateDescription(null)
            ->emptyStateActions([
                Action::make('hapusSemuaFilter')
                    ->label('Hapus semua filter')
                    ->color('gray')
                    ->visible(fn (HasTable $livewire): bool => self::adaFilterAktif($livewire))
                    ->action(fn (HasTable $livewire) => $livewire->removeTableFilters()),
            ]);
    }

    /**
     * Kolom panah pengganti kolom Aksi; seluruh baris sudah menjadi tautan ke halaman detail.
     */
    public static function kolomPanah(): IconColumn
    {
        return IconColumn::make('panah')
            ->label('Buka')
            ->state('buka')
            ->icon(Heroicon::ChevronRight)
            ->color('gray')
            ->width('36px');
    }

    public static function adaFilterAktif(HasTable $livewire): bool
    {
        return $livewire->getTable()->getFilterIndicators() !== [] || filled($livewire->getTableSearch());
    }
}
