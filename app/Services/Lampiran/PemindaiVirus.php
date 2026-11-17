<?php

namespace App\Services\Lampiran;

use App\Enums\StatusPindai;

/**
 * Pemindai virus lampiran (UC-17). Driver dipilih lewat config sigap.lampiran.pemindai.
 */
interface PemindaiVirus
{
    /**
     * @return array{status: StatusPindai, keterangan: string|null} status Bersih, Karantina, atau TidakDipindai
     *
     * @throws \RuntimeException bila pemindai gagal berjalan; job akan mencoba lagi
     */
    public function pindai(string $path): array;
}
