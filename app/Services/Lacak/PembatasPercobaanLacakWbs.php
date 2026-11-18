<?php

namespace App\Services\Lacak;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Pembatasan percobaan Lacak untuk kode WBS (H-06, Rancangan r1 Bagian 9) tanpa IP sama sekali (aturan proyek
 * no. 5). Dua penghitung:
 * - per kode lacak (hash kode): setelah 5 kali kode atau PIN salah dalam 15 menit, kode itu wajib CAPTCHA dan
 *   menunggu jeda sejak kegagalan terakhir;
 * - global untuk semua kode WBS: bila jumlah kegagalan melewati batas dalam 15 menit, semua percobaan WBS wajib
 *   CAPTCHA, agar penebakan yang berpindah-pindah kode tetap tertahan.
 * Kegagalan pada kode yang tidak ada dihitung sama dengan PIN salah, sehingga keberadaan kode tidak terungkap.
 */
class PembatasPercobaanLacakWbs
{
    private const KUNCI_GLOBAL = 'lacak-wbs:gagal:global';

    public static function perluCaptcha(string $kode): bool
    {
        return RateLimiter::attempts(self::kunciGagal($kode)) >= (int) config('sigap.lacak.maks_gagal')
            || RateLimiter::attempts(self::KUNCI_GLOBAL) >= (int) config('sigap.lacak.wbs_maks_gagal_global');
    }

    /**
     * Detik tersisa sebelum kode ini boleh dicoba lagi; 0 bila tidak sedang jeda.
     */
    public static function sisaJeda(string $kode): int
    {
        return max(0, (int) Cache::get(self::kunciJeda($kode), 0) - now()->getTimestamp());
    }

    public static function catatGagal(string $kode): void
    {
        $masa = (int) config('sigap.lacak.masa_gagal_menit') * 60;

        RateLimiter::hit(self::kunciGagal($kode), $masa);
        RateLimiter::hit(self::KUNCI_GLOBAL, $masa);

        if (RateLimiter::attempts(self::kunciGagal($kode)) >= (int) config('sigap.lacak.maks_gagal')) {
            $jeda = (int) config('sigap.lacak.jeda_detik');
            Cache::put(self::kunciJeda($kode), now()->getTimestamp() + $jeda, $jeda);
        }
    }

    /**
     * Setelah berhasil masuk, penghitung kode itu dihapus; penghitung global tetap berjalan sampai masanya habis.
     */
    public static function bersihkan(string $kode): void
    {
        RateLimiter::clear(self::kunciGagal($kode));
        Cache::forget(self::kunciJeda($kode));
    }

    private static function kunciGagal(string $kode): string
    {
        return 'lacak-wbs:gagal:'.hash('sha256', $kode);
    }

    private static function kunciJeda(string $kode): string
    {
        return 'lacak-wbs:jeda:'.hash('sha256', $kode);
    }
}
