<?php

namespace App\Services\Wbs;

/**
 * Token bertanda tangan untuk jalur /wbs* yang berjalan tanpa sesi, cookie, dan IP (aturan proyek no. 5). Isinya
 * data kecil dan waktu kedaluwarsa, ditandatangani HMAC dengan kunci aplikasi dan tujuan token, sehingga token satu
 * tujuan (misalnya akses formulir) tidak bisa dipakai untuk tujuan lain (misalnya Lacak). Server tidak menyimpan
 * apa pun; token hanya ada di memori halaman dan dikirim di badan permintaan atau header, tidak di URL.
 */
class TokenTanpaSesi
{
    /**
     * @param  array<string, int|string>  $data
     */
    public static function terbitkan(string $tujuan, array $data, int $menit): string
    {
        $isi = self::base64Url((string) json_encode([...$data, 'e' => now()->addMinutes($menit)->getTimestamp()]));

        return $isi.'.'.self::tandaTangan($tujuan, $isi);
    }

    /**
     * Data token bila tanda tangannya sah untuk tujuan ini dan belum kedaluwarsa; selain itu null.
     *
     * @return array<string, mixed>|null
     */
    public static function uraikan(string $tujuan, mixed $token): ?array
    {
        if (! is_string($token) || strlen($token) > 1000 || substr_count($token, '.') !== 1) {
            return null;
        }

        [$isi, $tanda] = explode('.', $token);

        if (! hash_equals(self::tandaTangan($tujuan, $isi), $tanda)) {
            return null;
        }

        $data = json_decode((string) base64_decode(strtr($isi, '-_', '+/'), true), true);

        if (! is_array($data) || ! is_int($data['e'] ?? null) || $data['e'] < now()->getTimestamp()) {
            return null;
        }

        return $data;
    }

    private static function tandaTangan(string $tujuan, string $isi): string
    {
        return self::base64Url(hash_hmac('sha256', $tujuan.'|'.$isi, (string) config('app.key'), true));
    }

    private static function base64Url(string $biner): string
    {
        return rtrim(strtr(base64_encode($biner), '+/', '-_'), '=');
    }
}
