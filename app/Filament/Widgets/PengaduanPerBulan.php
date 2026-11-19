<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Rekap\RekapLaporan;
use App\Services\WaktuLokal;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Grafik pengaduan per unit dan per bulan (H-10). PIC Unit dan Admin Sekolah hanya melihat unitnya, karena datanya
 * berasal dari Laporan::rekapPengaduanBagi().
 */
class PengaduanPerBulan extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Pengaduan per unit dan per bulan';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && RekapLaporan::bolehRekapPengaduan($user);
    }

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        $tahun = CarbonImmutable::now(WaktuLokal::zona())->year;

        return [(string) $tahun => (string) $tahun, (string) ($tahun - 1) => (string) ($tahun - 1)];
    }

    protected function getData(): array
    {
        $user = Filament::auth()->user();
        $pilihan = $this->getFilters();
        $tahun = (int) (array_key_exists((string) $this->filter, $pilihan) ? $this->filter : array_key_first($pilihan));

        $perUnit = $user instanceof User ? app(RekapLaporan::class)->pengaduanPerUnitPerBulan($user, $tahun) : [];

        return [
            'datasets' => collect($perUnit)->map(fn (array $bulan, string $unit): array => [
                'label' => $unit,
                'data' => array_values($bulan),
            ])->values()->all(),
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
