<?php

namespace App\Filament\Widgets;

use App\Enums\Peran;
use App\Filament\Pages\RekapEkspor;
use App\Filament\Resources\HasilWbs\HasilWbsResource;
use App\Filament\Resources\LaporanDialihkan\LaporanDialihkanResource;
use App\Filament\Resources\LaporanWbs\LaporanWbsResource;
use App\Filament\Resources\Pengaduan\PengaduanResource;
use App\Filament\Resources\PengaduanUnit\PengaduanUnitResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Laporan;
use App\Models\User;
use App\Services\Rekap\RekapLaporan;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Ringkasan dashboard sesuai peran (H-10, US-22): laporan baru, menunggu tindakan, dan lewat batas waktu. Angkanya
 * dihitung RekapLaporan dari scope hak akses pengguna. Setiap kartu membuka daftar dengan filter yang sama, dan
 * daftar itu tetap memakai scope hak akses resource-nya; kartu tanpa daftar yang boleh dibuka tidak bertautan.
 */
class RingkasanPeran extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.dasbor.ringkasan-peran';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && app(RekapLaporan::class)->ringkasan($user) !== [];
    }

    /**
     * @return array{kartu: list<array{label: string, nilai: int, keterangan: string, url: string|null, peringatan: bool}>}
     */
    protected function getViewData(): array
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return ['kartu' => []];
        }

        return [
            'kartu' => array_map(fn (array $kartu): array => [
                ...$kartu,
                'url' => self::tautan($user, $kartu['label']),
                'peringatan' => str_contains(mb_strtolower($kartu['label']), 'lewat batas waktu') && $kartu['nilai'] > 0,
            ], app(RekapLaporan::class)->ringkasan($user)),
        ];
    }

    /**
     * Daftar tujuan setiap kartu. Filter yang dipakai sama dengan hitungan RekapLaporan::ringkasan().
     */
    public static function tautan(User $user, string $label): ?string
    {
        $kotakMasuk = fn (array $filter): string => PengaduanResource::getUrl('index', ['filters' => $filter]);
        $unitSaya = fn (array $filter): string => PengaduanUnitResource::getUrl('index', ['filters' => $filter]);
        $wbs = fn (array $filter): string => LaporanWbsResource::getUrl('index', ['filters' => $filter]);

        return match (true) {
            $user->peran === Peran::AdminPengaduan => match ($label) {
                'Pengaduan baru' => $kotakMasuk(['status' => ['value' => 'diterima']]),
                'Menunggu disposisi' => $kotakMasuk(['status' => ['value' => 'terverifikasi']]),
                'Lewat batas waktu' => $kotakMasuk(['lewat_batas' => ['isActive' => 1]]),
                default => null,
            },
            $user->peran === Peran::PicUnit => match ($label) {
                'Pengaduan baru' => $unitSaya(['belum_ditindaklanjuti' => ['isActive' => 1]]),
                'Menunggu tindakan Anda' => $unitSaya(['status' => ['value' => 'diproses']]),
                'Lewat batas waktu' => $unitSaya(['lewat_batas' => ['isActive' => 1]]),
                default => null,
            },
            in_array($user->peran, [Peran::KetuaTimWbs, Peran::AnggotaTimWbs], true) => match ($label) {
                'Laporan WBS baru' => $wbs(['status' => ['value' => 'diterima']]),
                'Sedang ditangani' => $wbs(['sedang_ditangani' => ['isActive' => 1]]),
                'Lewat batas waktu' => $wbs(['lewat_batas' => ['isActive' => 1]]),
                default => null,
            },
            in_array($user->peran, Laporan::PERAN_REKAP_AGREGAT, true) => match ($label) {
                'Hasil WBS menunggu tindak lanjut' => HasilWbsResource::getUrl('index', ['filters' => ['belum_ditindaklanjuti' => ['isActive' => 1]]]),
                default => RekapEkspor::canAccess() ? RekapEkspor::getUrl() : null,
            },
            in_array($user->peran, [Peran::KepalaSekolah, Peran::KomiteEtik], true) => match ($label) {
                'Pengalihan belum dikonfirmasi' => LaporanDialihkanResource::getUrl('index', ['filters' => ['dikonfirmasi' => ['value' => 0]]]),
                'Pengalihan diterima' => LaporanDialihkanResource::getUrl('index', ['filters' => ['dikonfirmasi' => ['value' => 1]]]),
                default => null,
            },
            $user->peran === Peran::SuperAdmin => $label === 'Akun terkunci'
                ? UserResource::getUrl('index', ['filters' => ['terkunci' => ['isActive' => 1]]])
                : null,
            default => null,
        };
    }
}
