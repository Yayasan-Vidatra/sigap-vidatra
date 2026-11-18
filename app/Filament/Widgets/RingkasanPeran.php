<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\Rekap\RekapLaporan;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan dashboard sesuai peran (H-10, US-22): laporan baru, lewat batas waktu, dan menunggu tindakan. Angkanya
 * dihitung RekapLaporan dari scope hak akses pengguna.
 */
class RingkasanPeran extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Ringkasan';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && app(RekapLaporan::class)->ringkasan($user) !== [];
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return array_map(
            fn (array $kartu): Stat => Stat::make($kartu['label'], (string) $kartu['nilai'])->description($kartu['keterangan']),
            app(RekapLaporan::class)->ringkasan($user),
        );
    }
}
