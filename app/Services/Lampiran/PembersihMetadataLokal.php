<?php

namespace App\Services\Lampiran;

use App\Enums\JenisBerkas;
use Illuminate\Support\Facades\Log;

/**
 * Driver "lokal" untuk Windows/Laragon tanpa ExifTool/FFmpeg. Tidak membersihkan metadata dan
 * mencatatnya sebagai peringatan di log.
 */
class PembersihMetadataLokal implements PembersihMetadata
{
    public function bersihkan(string $path, JenisBerkas $jenis, string $mime): bool
    {
        Log::warning('Metadata lampiran TIDAK DIBERSIHKAN: driver pembersih "lokal" aktif (LAMPIRAN_PEMBERSIH_METADATA=lokal).', [
            'mime' => $mime,
        ]);

        return false;
    }
}
