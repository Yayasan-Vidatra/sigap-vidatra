<?php

namespace App\Rules;

use App\Enums\JenisLaporan;
use App\Services\Lampiran\UnggahanBertahap;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Setiap id dalam daftar lampiran harus unggahan kanal ini yang sudah selesai diperiksa, dan totalnya
 * paling besar sigap.lampiran.maks_total_mb (Rancangan r1 Bagian 8).
 */
class UnggahanLampiranSiap implements ValidationRule
{
    public function __construct(private JenisLaporan $kanal) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $total = 0;

        foreach ($value as $id) {
            $siap = app(UnggahanBertahap::class)->ambilSiap((string) $id, $this->kanal);

            if ($siap === null) {
                $fail('Ada lampiran yang belum selesai diunggah atau sudah kedaluwarsa. Hapus lalu unggah ulang.');

                return;
            }

            $total += $siap['ukuran'];
        }

        if ($total > (int) config('sigap.lampiran.maks_total_mb') * 1024 * 1024) {
            $fail('Total ukuran lampiran paling besar '.config('sigap.lampiran.maks_total_mb').' MB.');
        }
    }
}
