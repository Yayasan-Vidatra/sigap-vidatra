<?php

namespace App\Services\Lacak;

use App\Models\Laporan;
use Illuminate\Http\Request;

/**
 * Izin membuka Detail Laporan (H-07) disimpan di sesi setelah pelapor lolos H-06, bukan hanya di URL. Izin
 * berlaku untuk satu laporan dan berakhir setelah 15 menit tidak aktif (TOR H-07); setiap permintaan ke
 * halaman detail memperpanjangnya.
 */
class IzinLacak
{
    private const KUNCI_SESI = 'lacak.izin';

    public static function berikan(Request $request, Laporan $laporan): void
    {
        $request->session()->regenerate();
        $request->session()->put(self::KUNCI_SESI, [
            'laporan_id' => $laporan->getKey(),
            'kode_lacak' => $laporan->kode_lacak,
            'aktif_pada' => now()->getTimestamp(),
        ]);
    }

    /**
     * Laporan yang boleh dibuka sesi ini, atau null bila izinnya tidak ada atau sudah berakhir (izin yang
     * berakhir sekaligus dihapus). Laporan ditentukan dari sesi, bukan dari URL.
     */
    public static function laporan(Request $request): ?Laporan
    {
        $izin = $request->session()->get(self::KUNCI_SESI);

        if (! is_array($izin)) {
            return null;
        }

        if (now()->getTimestamp() - (int) ($izin['aktif_pada'] ?? 0) > self::masaMenit() * 60) {
            self::cabut($request);

            return null;
        }

        $laporan = Laporan::query()->find($izin['laporan_id'] ?? null);

        if ($laporan === null || $laporan->kode_lacak !== ($izin['kode_lacak'] ?? null)) {
            self::cabut($request);

            return null;
        }

        $request->session()->put(self::KUNCI_SESI.'.aktif_pada', now()->getTimestamp());

        return $laporan;
    }

    public static function pernahAda(Request $request): bool
    {
        return $request->session()->has(self::KUNCI_SESI);
    }

    public static function cabut(Request $request): void
    {
        $request->session()->forget(self::KUNCI_SESI);
    }

    public static function masaMenit(): int
    {
        return (int) config('sigap.lacak.masa_sesi_menit');
    }
}
