<?php

namespace App\Services\Lacak;

use App\Enums\JenisLaporan;
use App\Models\Laporan;
use App\Services\Wbs\TokenTanpaSesi;

/**
 * Izin membuka Lacak WBS (/wbs/lacak, revisi 20-11) tanpa sesi dan cookie: setelah kode lacak dan PIN cocok,
 * halaman menerima token bertanda tangan yang memuat id laporan dan kedaluwarsa. Setiap jawaban server membawa
 * token baru, sehingga izin berakhir setelah masa sesi Lacak (15 menit) tanpa aktivitas, sama seperti Lacak
 * Pengaduan. Token hanya ada di memori halaman; kode lacak tidak pernah ada di URL.
 */
class TokenLacakWbs
{
    private const TUJUAN = 'lacak-wbs';

    public static function terbitkan(Laporan $laporan): string
    {
        return TokenTanpaSesi::terbitkan(self::TUJUAN, ['l' => (int) $laporan->getKey()], self::masaMenit());
    }

    /**
     * Laporan WBS milik token, atau null bila token tidak sah, kedaluwarsa, atau laporannya bukan WBS.
     */
    public static function laporan(mixed $token): ?Laporan
    {
        $data = TokenTanpaSesi::uraikan(self::TUJUAN, $token);

        if ($data === null || ! is_int($data['l'] ?? null)) {
            return null;
        }

        return Laporan::query()->whereKey($data['l'])->where('jenis', JenisLaporan::Wbs->value)->first();
    }

    public static function masaMenit(): int
    {
        return (int) config('sigap.lacak.masa_sesi_menit');
    }
}
