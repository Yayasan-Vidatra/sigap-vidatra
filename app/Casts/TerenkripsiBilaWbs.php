<?php

namespace App\Casts;

use App\Enums\JenisLaporan;
use App\Exceptions\PelanggaranIntegritasData;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Mengenkripsi kolom laporan hanya bila jenis = wbs; pengaduan disimpan sebagai teks biasa agar
 * bisa dicari (prd/log-deviasi.md, klarifikasi UC-02 dan Bagian 9).
 *
 * Kolom jenis wajib ikut dimuat. Tanpa jenis, cast tidak tahu apakah nilai harus didekripsi,
 * sehingga menolak bekerja alih-alih menebak.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class TerenkripsiBilaWbs implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->isWbs($key, $attributes) ? Crypt::decryptString($value) : $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->isWbs($key, $attributes) ? Crypt::encryptString((string) $value) : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function isWbs(string $key, array $attributes): bool
    {
        if (! isset($attributes['jenis'])) {
            throw PelanggaranIntegritasData::jenisBelumDiisi($key);
        }

        $jenis = $attributes['jenis'] instanceof JenisLaporan
            ? $attributes['jenis']
            : JenisLaporan::from($attributes['jenis']);

        return $jenis === JenisLaporan::Wbs;
    }
}
