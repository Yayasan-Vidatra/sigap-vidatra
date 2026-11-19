<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Rekap\RekapLaporan;
use App\Services\WaktuLokal;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Jumlah WBS per kategori per triwulan untuk Pimpinan Yayasan dan Penerima Hasil (H-10, F-13). Sel kurang dari 3
 * tampil "< 3"; tidak ada rincian, total, atau tautan ke laporan.
 */
class WbsPerTriwulan extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.wbs-per-triwulan';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && RekapLaporan::bolehRekapWbs($user);
    }

    /**
     * @return array{tahun: int, baris: list<array{kategori: string, triwulan: array<int, int|null>}>, minimal: int}
     */
    protected function getViewData(): array
    {
        $user = Filament::auth()->user();
        $tahun = CarbonImmutable::now(WaktuLokal::zona())->year;

        return [
            'tahun' => $tahun,
            'baris' => $user instanceof User ? app(RekapLaporan::class)->wbsPerKategoriPerTriwulan($user, $tahun) : [],
            'minimal' => RekapLaporan::MINIMAL_WBS,
        ];
    }
}
