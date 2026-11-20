<?php

namespace App\Filament\Concerns;

use App\Enums\StatusLaporan;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tombol status dengan jumlah di atas tabel daftar (Kotak Masuk Pengaduan, Ruang Kerja WBS), sesuai prototipe
 * prd/mockup/panel-*.html. Tombol mengisi filter tabel `status`, sehingga label filter aktif, "Hapus semua filter",
 * dan alamat ?filters[status][value]=… tetap bekerja seperti filter lain.
 *
 * Jumlah per status dihitung dari query resource (getEloquentQuery, yang sudah memuat scope hak akses), lalu
 * dipersempit filter dan pencarian lain yang sedang aktif. Tidak ada jalur yang memperluas cakupan data.
 */
trait MenampilkanTombolStatus
{
    /**
     * Status yang punya tombol, berkunci nilai status.
     *
     * @return array<string, string>
     */
    abstract protected function pilihanTombolStatus(): array;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.tabel.tombol-status')
                    ->viewData(fn (): array => ['tombol' => $this->tombolStatus()]),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    /**
     * @return list<array{nilai: string|null, label: string, jumlah: int, aktif: bool}>
     */
    public function tombolStatus(): array
    {
        $aktif = $this->getTableFilterState('status')['value'] ?? null;
        $aktif = filled($aktif) ? (string) $aktif : null;
        $jumlah = $this->jumlahPerStatus();

        $tombol = [[
            'nilai' => null,
            'label' => 'Semua',
            'jumlah' => array_sum($jumlah),
            'aktif' => $aktif === null,
        ]];

        foreach ($this->pilihanTombolStatus() as $nilai => $label) {
            $tombol[] = [
                'nilai' => (string) $nilai,
                'label' => $label,
                'jumlah' => $jumlah[$nilai] ?? 0,
                'aktif' => $aktif === (string) $nilai,
            ];
        }

        // Status jarang (misalnya pengalihan) hanya mendapat tombol bila ada laporannya atau sedang dipilih.
        foreach (array_diff_key($jumlah + ($aktif !== null ? [$aktif => 0] : []), $this->pilihanTombolStatus()) as $nilai => $banyak) {
            $status = StatusLaporan::tryFrom((string) $nilai);

            if ($status === null) {
                continue;
            }

            $tombol[] = [
                'nilai' => $status->value,
                'label' => $status->getLabel(),
                'jumlah' => $banyak,
                'aktif' => $aktif === $status->value,
            ];
        }

        return $tombol;
    }

    /**
     * @return array<string, int>
     */
    protected function jumlahPerStatus(): array
    {
        $query = static::getResource()::getEloquentQuery();
        $table = $this->getTable();

        $table->withAppliedFiltersFormState(function () use ($query, $table): void {
            $query->where(function (Builder $query) use ($table): void {
                foreach ($table->getFilters() as $nama => $filter) {
                    if ($nama === 'status') {
                        continue;
                    }

                    $filter->apply($query, $this->getTableFilterState($nama) ?? []);
                }
            });
        });

        $this->applySearchToTableQuery($query);

        return $query
            ->toBase()
            ->reorder()
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status')
            ->map(fn (mixed $jumlah): int => (int) $jumlah)
            ->all();
    }
}
