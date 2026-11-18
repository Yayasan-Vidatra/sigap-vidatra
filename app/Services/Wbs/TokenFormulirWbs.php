<?php

namespace App\Services\Wbs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Token formulir WBS bertanda tangan berumur pendek, pengganti token CSRF di jalur /wbs* yang berjalan tanpa
 * sesi dan cookie (aturan proyek no. 5). Token berisi nonce acak dan waktu kedaluwarsa, ditandatangani HMAC
 * dengan kunci aplikasi; tidak memuat apa pun tentang pelapor. Token dipakai untuk unggah lampiran WBS dan
 * pengiriman formulir, lalu ditandai terpakai saat laporan terkirim sehingga tidak bisa dipakai ulang.
 * Halaman formulir meminta token baru secara berkala selama masih terbuka.
 */
class TokenFormulirWbs
{
    private const AWALAN_CACHE = 'wbs:token-formulir-dipakai:';

    public static function terbitkan(): string
    {
        $isi = self::base64Url((string) json_encode([
            'n' => Str::random(32),
            'e' => now()->addMinutes(self::masaMenit())->getTimestamp(),
        ]));

        return $isi.'.'.self::tandaTangan($isi);
    }

    /**
     * Nonce token bila tanda tangannya sah, belum kedaluwarsa, dan belum dipakai; selain itu null.
     */
    public static function nonce(?string $token): ?string
    {
        $data = self::uraikan($token);

        if ($data === null || Cache::has(self::AWALAN_CACHE.$data['n'])) {
            return null;
        }

        return $data['n'];
    }

    /**
     * Menandai token terpakai sampai kedaluwarsa. Atomik: dari dua kiriman bersamaan hanya satu yang berhasil.
     *
     * @return bool false bila token tidak sah, kedaluwarsa, atau sudah dipakai
     */
    public static function pakai(?string $token): bool
    {
        $data = self::uraikan($token);

        if ($data === null) {
            return false;
        }

        return Cache::add(self::AWALAN_CACHE.$data['n'], true, max(1, $data['e'] - now()->getTimestamp()) + 60);
    }

    public static function masaMenit(): int
    {
        return (int) config('sigap.wbs.masa_token_formulir_menit');
    }

    /**
     * @return array{n: string, e: int}|null
     */
    private static function uraikan(?string $token): ?array
    {
        if (! is_string($token) || substr_count($token, '.') !== 1) {
            return null;
        }

        [$isi, $tanda] = explode('.', $token);

        if (! hash_equals(self::tandaTangan($isi), $tanda)) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($isi, '-_', '+/'), true), true);

        if (! is_array($data) || ! is_string($data['n'] ?? null) || ! is_int($data['e'] ?? null)) {
            return null;
        }

        if ($data['e'] < now()->getTimestamp()) {
            return null;
        }

        return ['n' => $data['n'], 'e' => $data['e']];
    }

    private static function tandaTangan(string $isi): string
    {
        return self::base64Url(hash_hmac('sha256', 'token-formulir-wbs|'.$isi, (string) config('app.key'), true));
    }

    private static function base64Url(string $biner): string
    {
        return rtrim(strtr(base64_encode($biner), '+/', '-_'), '=');
    }
}
