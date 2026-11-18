<?php

namespace App\Services\Wbs;

use App\Models\Laporan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Token unduh PDF bukti lapor WBS (H-05, US-07), pengganti izin di sesi yang dipakai pengaduan. Token acak
 * tidak memuat kode lacak; kode lacak dan waktu kirim disimpan terenkripsi di cache dengan kunci hash token,
 * berlaku masa_unduh_pdf_menit dan hanya bisa dipakai sekali.
 */
class TokenUnduhPdfWbs
{
    private const AWALAN_DATA = 'wbs:pdf:';

    private const AWALAN_DIPAKAI = 'wbs:pdf-dipakai:';

    public static function terbitkan(Laporan $laporan): string
    {
        $token = Str::random(48);

        Cache::put(self::AWALAN_DATA.self::kunci($token), Crypt::encryptString((string) json_encode([
            'kode_lacak' => $laporan->kode_lacak,
            'dikirim_pada' => $laporan->dibuat_pada->getTimestamp(),
        ])), now()->addMinutes(self::masaMenit()));

        return $token;
    }

    /**
     * Data bukti lapor bila token sah dan belum dipakai; token langsung tidak berlaku lagi.
     *
     * @return array{kode_lacak: string, dikirim_pada: int}|null
     */
    public static function pakai(?string $token): ?array
    {
        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return null;
        }

        $kunci = self::kunci($token);
        $terenkripsi = Cache::get(self::AWALAN_DATA.$kunci);

        if (! is_string($terenkripsi) || ! Cache::add(self::AWALAN_DIPAKAI.$kunci, true, now()->addMinutes(self::masaMenit()))) {
            return null;
        }

        Cache::forget(self::AWALAN_DATA.$kunci);
        $data = json_decode(Crypt::decryptString($terenkripsi), true);

        return ['kode_lacak' => (string) $data['kode_lacak'], 'dikirim_pada' => (int) $data['dikirim_pada']];
    }

    public static function masaMenit(): int
    {
        return (int) config('sigap.bukti_lapor.masa_unduh_pdf_menit');
    }

    private static function kunci(string $token): string
    {
        return hash('sha256', $token);
    }
}
