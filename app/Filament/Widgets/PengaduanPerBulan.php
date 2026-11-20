<?php

namespace App\Filament\Widgets;

use App\Models\Unit;
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
    protected static ?int $sort = 3;

    protected ?string $heading = 'Pengaduan per unit dan per bulan';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    /**
     * Warna per unit (palet kategori yang lolos uji buta warna): mengikuti urutan unit di basis data, bukan urutan
     * data, sehingga warna satu unit tidak berubah saat tahun berganti. Angka pastinya ada di tabel Rekap (H-18).
     *
     * @var list<string>
     */
    private const WARNA_UNIT = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];

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

        $urutanUnit = Unit::query()->orderBy('id')->pluck('nama')->flip();

        return [
            'datasets' => collect($perUnit)->map(function (array $bulan, string $unit) use ($urutanUnit): array {
                $warna = self::WARNA_UNIT[($urutanUnit[$unit] ?? 0) % count(self::WARNA_UNIT)];

                return [
                    'label' => $unit,
                    'data' => array_values($bulan),
                    'backgroundColor' => $warna,
                    'borderColor' => $warna,
                    'borderWidth' => 0,
                    'borderRadius' => 4,
                    'maxBarThickness' => 18,
                ];
            })->values()->all(),
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
