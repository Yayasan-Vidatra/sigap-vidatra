<?php

namespace Tests\Fixtures;

use App\Filament\Concerns\MencatatPembukaanLaporan;
use App\Models\Laporan;
use Livewire\Component;

/**
 * Halaman record laporan tiruan untuk menguji MencatatPembukaanLaporan sebelum halaman laporan
 * Filament yang sebenarnya dibuat.
 */
class HalamanLaporanUji extends Component
{
    use MencatatPembukaanLaporan;

    public Laporan $record;

    public function mount(Laporan $record): void
    {
        $this->record = $record;
    }

    public function getRecord(): Laporan
    {
        return $this->record;
    }

    public function render(): string
    {
        return '<div>Halaman laporan uji</div>';
    }
}
