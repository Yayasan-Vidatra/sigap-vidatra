<?php

namespace App\Services\Lacak;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Pembatasan percobaan di Lacak Laporan (H-06, Rancangan r1 Bagian 9): setelah 5 kali kode salah dalam 15
 * menit, percobaan berikutnya wajib CAPTCHA ALTCHA dan harus menunggu jeda sejak kegagalan terakhir.
 * Kunci pembatas memakai hash alamat IP; IP tidak disimpan di basis data. Kanal WBS memakai jalurnya
 * sendiri tanpa IP (Hari 9).
 */
class PembatasPercobaanLacak
{
    public static function perluCaptcha(Request $request): bool
    {
        return RateLimiter::attempts(self::kunciGagal($request)) >= (int) config('sigap.lacak.maks_gagal');
    }

    /**
     * Detik tersisa sebelum percobaan berikutnya boleh dicoba; 0 bila tidak sedang jeda.
     */
    public static function sisaJeda(Request $request): int
    {
        $sampai = (int) Cache::get(self::kunciJeda($request), 0);

        return max(0, $sampai - now()->getTimestamp());
    }

    public static function catatGagal(Request $request): void
    {
        RateLimiter::hit(self::kunciGagal($request), (int) config('sigap.lacak.masa_gagal_menit') * 60);

        if (self::perluCaptcha($request)) {
            $jeda = (int) config('sigap.lacak.jeda_detik');
            Cache::put(self::kunciJeda($request), now()->getTimestamp() + $jeda, $jeda);
        }
    }

    public static function bersihkan(Request $request): void
    {
        RateLimiter::clear(self::kunciGagal($request));
        Cache::forget(self::kunciJeda($request));
    }

    private static function kunciGagal(Request $request): string
    {
        return 'lacak:gagal:'.hash('sha256', (string) $request->ip());
    }

    private static function kunciJeda(Request $request): string
    {
        return 'lacak:jeda:'.hash('sha256', (string) $request->ip());
    }
}
