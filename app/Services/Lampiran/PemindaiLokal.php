<?php

namespace App\Services\Lampiran;

use App\Enums\StatusPindai;
use Illuminate\Support\Facades\Log;

/**
 * Driver "lokal" untuk Windows/Laragon tanpa ClamAV. Tidak memindai apa pun: hasilnya TidakDipindai
 * dan tercatat sebagai peringatan di log, bukan dianggap bersih.
 */
class PemindaiLokal implements PemindaiVirus
{
    public function pindai(string $path): array
    {
        Log::warning('Lampiran TIDAK DIPINDAI: driver pemindai "lokal" aktif (LAMPIRAN_PEMINDAI=lokal). Pasang ClamAV dan pakai driver "alat" di server.');

        return ['status' => StatusPindai::TidakDipindai, 'keterangan' => 'Driver lokal: tidak dipindai'];
    }
}
