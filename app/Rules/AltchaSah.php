<?php

namespace App\Rules;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

/**
 * Memverifikasi solusi CAPTCHA ALTCHA dari widget: tanda tangan HMAC sah, belum kedaluwarsa, dan
 * kunci turunannya benar. Dipakai di formulir Pengaduan dan nanti di formulir WBS (H-04).
 *
 * Aturan ini tidak menandai solusi sebagai terpakai, agar pelapor yang salah mengisi kolom lain bisa
 * mengirim ulang tanpa memecahkan CAPTCHA lagi. Tandai dengan tandaiDipakai() setelah seluruh isian sah.
 */
class AltchaSah implements ValidationRule
{
    private const AWALAN_KUNCI_CACHE = 'altcha:dipakai:';

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Selesaikan verifikasi "Saya bukan robot" terlebih dahulu.');

            return;
        }

        try {
            $hasil = app(Altcha::class)->verifySolution(new VerifySolutionOptions(
                algorithm: new Pbkdf2,
                payload: $value,
            ));
        } catch (Throwable) {
            $fail('Verifikasi "Saya bukan robot" tidak sah. Silakan ulangi.');

            return;
        }

        if ($hasil->expired) {
            $fail('Verifikasi "Saya bukan robot" sudah kedaluwarsa. Silakan ulangi.');

            return;
        }

        if (! $hasil->verified) {
            $fail('Verifikasi "Saya bukan robot" tidak sah. Silakan ulangi.');

            return;
        }

        if (Cache::has(self::kunciCache($value))) {
            $fail('Verifikasi "Saya bukan robot" sudah dipakai. Silakan ulangi.');
        }
    }

    /**
     * Menandai solusi sebagai terpakai sampai tantangannya kedaluwarsa. Atomik: bila dua kiriman memakai
     * solusi yang sama bersamaan, hanya satu yang berhasil.
     *
     * @return bool false bila solusi ini sudah pernah dipakai
     */
    public static function tandaiDipakai(string $payload): bool
    {
        $kedaluwarsa = (float) (Payload::fromBase64($payload)->challenge->parameters->expiresAt ?? 0);
        $sisaDetik = max(1, (int) ceil($kedaluwarsa - microtime(true)) + 60);

        return Cache::add(self::kunciCache($payload), true, $sisaDetik);
    }

    private static function kunciCache(string $payload): string
    {
        $tandaTangan = Payload::fromBase64($payload)->challenge->signature ?? $payload;

        return self::AWALAN_KUNCI_CACHE.hash('sha256', $tandaTangan);
    }
}
