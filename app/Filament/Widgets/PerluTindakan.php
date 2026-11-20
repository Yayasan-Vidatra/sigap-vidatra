<?php

namespace App\Filament\Widgets;

use App\Enums\StatusLaporan;
use App\Filament\Resources\HasilWbs\HasilWbsResource;
use App\Filament\Resources\LaporanDialihkan\LaporanDialihkanResource;
use App\Filament\Resources\LaporanWbs\LaporanWbsResource;
use App\Filament\Resources\LaporanWbs\Tables\LaporanWbsTable;
use App\Filament\Resources\Pengaduan\PengaduanResource;
use App\Filament\Resources\Pengaduan\Tables\PengaduanTable;
use App\Filament\Resources\PengaduanUnit\PengaduanUnitResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\WarnaPanel;
use App\Models\Laporan;
use App\Models\Pengalihan;
use App\Models\User;
use App\Services\Rekap\RekapLaporan;
use App\Services\Wbs\PenangananWbs;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Daftar singkat "Perlu tindakan Anda" di Dasbor (H-10): lima laporan teratas yang menunggu tindakan pengguna, dengan
 * tautan "Lihat semua" ke daftar lengkapnya. Setiap bagian hanya muncul bila pengguna boleh membuka daftarnya
 * (canViewAny), dan barisnya diambil dari query resource yang sama (getEloquentQuery), sehingga tidak ada laporan
 * di luar hak akses yang tampil. Laporan WBS hanya ditampilkan dengan kode lacak dan jenis pelanggaran.
 */
class PerluTindakan extends Widget
{
    public const BATAS = 5;

    protected static ?int $sort = 2;

    protected string $view = 'filament.dasbor.perlu-tindakan';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return self::bagian() !== [];
    }

    /**
     * @return array{bagian: list<array{judul: string, url: string, kosong: string, baris: list<array<string, mixed>>}>}
     */
    protected function getViewData(): array
    {
        return ['bagian' => self::bagian()];
    }

    /**
     * @return list<array{judul: string, url: string, kosong: string, baris: list<array<string, mixed>>}>
     */
    public static function bagian(): array
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $bagian = [];

        if (PengaduanResource::canViewAny()) {
            $bagian[] = [
                'judul' => 'Pengaduan menunggu verifikasi atau disposisi',
                'url' => PengaduanResource::getUrl('index'),
                'kosong' => 'Tidak ada pengaduan yang menunggu verifikasi atau disposisi.',
                'baris' => self::barisPengaduan(
                    PengaduanResource::getEloquentQuery()
                        ->whereIn('status', [StatusLaporan::Diterima->value, StatusLaporan::Terverifikasi->value])
                        ->oldest('dibuat_pada')
                        ->limit(self::BATAS)
                        ->get(),
                    fn (Laporan $laporan): string => PengaduanResource::getUrl('view', ['record' => $laporan]),
                ),
            ];
        }

        if (PengaduanUnitResource::canViewAny()) {
            $bagian[] = [
                'judul' => 'Pengaduan yang sedang diproses unit Anda',
                'url' => PengaduanUnitResource::getUrl('index', ['filters' => ['status' => ['value' => StatusLaporan::Diproses->value]]]),
                'kosong' => 'Tidak ada pengaduan yang menunggu tindak lanjut.',
                'baris' => self::barisPengaduan(
                    PengaduanUnitResource::getEloquentQuery()
                        ->where('status', StatusLaporan::Diproses->value)
                        ->orderBy('batas_waktu')
                        ->limit(self::BATAS)
                        ->get(),
                    fn (Laporan $laporan): string => PengaduanUnitResource::getUrl('view', ['record' => $laporan]),
                ),
            ];
        }

        if (LaporanWbsResource::canViewAny()) {
            $berjalan = array_map(fn (StatusLaporan $status): string => $status->value, RekapLaporan::STATUS_WBS_BERJALAN);

            $bagian[] = [
                'judul' => 'Laporan WBS yang sedang berjalan',
                'url' => LaporanWbsResource::getUrl('index'),
                'kosong' => 'Tidak ada laporan WBS yang sedang berjalan.',
                'baris' => LaporanWbsResource::getEloquentQuery()
                    ->whereIn('status', $berjalan)
                    ->orderBy('batas_waktu')
                    ->limit(self::BATAS)
                    ->get()
                    ->map(fn (Laporan $laporan): array => [
                        'kode' => $laporan->kode_lacak,
                        'judul' => $laporan->kategori->nama ?? 'Jenis pelanggaran belum diisi',
                        'keterangan' => $laporan->unit->nama ?? 'Unit tidak diketahui',
                        'status' => $laporan->status->getLabel(),
                        'warna' => WarnaPanel::status($laporan->status),
                        'batas' => $laporan->batas_waktu?->translatedFormat('j M Y'),
                        'lewat' => LaporanWbsTable::lewatBatas($laporan),
                        'url' => LaporanWbsResource::getUrl('view', ['record' => $laporan]),
                    ])
                    ->all(),
            ];
        }

        if (HasilWbsResource::canViewAny()) {
            $bagian[] = [
                'judul' => 'Hasil WBS yang menunggu keputusan Anda',
                'url' => HasilWbsResource::getUrl('index', ['filters' => ['belum_ditindaklanjuti' => ['isActive' => 1]]]),
                'kosong' => 'Tidak ada hasil WBS yang menunggu keputusan Anda.',
                'baris' => HasilWbsResource::getEloquentQuery()
                    ->latest('diperbarui_pada')
                    ->get()
                    ->map(fn (Laporan $laporan): array => ['laporan' => $laporan, 'keperluan' => match (true) {
                        PenangananWbs::bisa('putuskanPerpanjangan', $laporan) => 'Persetujuan perpanjangan',
                        PenangananWbs::bisa('tandaiDitindaklanjuti', $laporan) => 'Tindak lanjut hasil',
                        default => null,
                    }])
                    ->filter(fn (array $item): bool => $item['keperluan'] !== null)
                    ->take(self::BATAS)
                    ->map(fn (array $item): array => [
                        'kode' => "#{$item['laporan']->getKey()}",
                        'judul' => $item['laporan']->kategori->nama ?? 'Jenis pelanggaran belum diisi',
                        'keterangan' => $item['laporan']->unit->nama ?? 'Unit tidak diketahui',
                        'status' => $item['keperluan'],
                        'warna' => 'status-diproses',
                        'batas' => $item['laporan']->batas_waktu?->translatedFormat('j M Y'),
                        'lewat' => false,
                        'url' => HasilWbsResource::getUrl('view', ['record' => $item['laporan']]),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        if (LaporanDialihkanResource::canViewAny()) {
            $bagian[] = [
                'judul' => 'Pengalihan yang belum Anda konfirmasi',
                'url' => LaporanDialihkanResource::getUrl('index', ['filters' => ['dikonfirmasi' => ['value' => 0]]]),
                'kosong' => 'Semua pengalihan sudah dikonfirmasi.',
                'baris' => LaporanDialihkanResource::getEloquentQuery()
                    ->whereNull('diterima_pada')
                    ->oldest('dibuat_pada')
                    ->limit(self::BATAS)
                    ->get()
                    ->map(fn (Pengalihan $pengalihan): array => [
                        'kode' => "#{$pengalihan->getKey()}",
                        'judul' => $pengalihan->laporan->kategori->nama ?? 'Kategori belum diisi',
                        'keterangan' => $pengalihan->unitTujuan->nama ?? $pengalihan->laporan->jenis->getLabel(),
                        'status' => 'Belum dikonfirmasi',
                        'warna' => 'status-menunggu',
                        'batas' => null,
                        'lewat' => false,
                        'url' => LaporanDialihkanResource::getUrl('view', ['record' => $pengalihan]),
                    ])
                    ->all(),
            ];
        }

        if (UserResource::canViewAny()) {
            $bagian[] = [
                'judul' => 'Akun terkunci',
                'url' => UserResource::getUrl('index', ['filters' => ['terkunci' => ['isActive' => 1]]]),
                'kosong' => 'Tidak ada akun yang terkunci.',
                'baris' => UserResource::getEloquentQuery()
                    ->whereNotNull('terkunci_pada')
                    ->oldest('terkunci_pada')
                    ->limit(self::BATAS)
                    ->get()
                    ->map(fn (User $akun): array => [
                        'kode' => $akun->name,
                        'judul' => $akun->email,
                        'keterangan' => $akun->peran?->getLabel() ?? 'Nonaktif',
                        'status' => 'Terkunci',
                        'warna' => 'status-diproses',
                        'batas' => null,
                        'lewat' => false,
                        'url' => UserResource::getUrl('index', ['filters' => ['terkunci' => ['isActive' => 1]]]),
                    ])
                    ->all(),
            ];
        }

        return $bagian;
    }

    /**
     * @param  Collection<int, Laporan>  $laporan
     * @param  callable(Laporan): string  $url
     * @return list<array<string, mixed>>
     */
    private static function barisPengaduan(Collection $laporan, callable $url): array
    {
        return $laporan->map(fn (Laporan $item): array => [
            'kode' => $item->kode_lacak,
            'judul' => $item->judul,
            'keterangan' => collect([$item->unit->nama ?? null, $item->kategori->nama ?? null])->filter()->implode(' · '),
            'status' => $item->status->getLabel(),
            'warna' => WarnaPanel::status($item->status),
            'batas' => $item->batas_waktu?->translatedFormat('j M Y'),
            'lewat' => PengaduanTable::lewatBatas($item),
            'url' => $url($item),
        ])->values()->all();
    }
}
