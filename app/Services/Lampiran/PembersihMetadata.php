<?php

namespace App\Services\Lampiran;

use App\Enums\JenisBerkas;

/**
 * Penghapus metadata lampiran (EXIF/lokasi GPS foto, metadata PDF dan video) (UC-17, Rancangan r1 Bagian 8).
 * Driver dipilih lewat config sigap.lampiran.pembersih_metadata.
 */
interface PembersihMetadata
{
    /**
     * Membersihkan metadata berkas di path (ditimpa di tempat).
     *
     * @return bool true bila metadata dibersihkan, false bila tidak (driver lokal atau format tanpa dukungan)
     *
     * @throws \RuntimeException bila alat gagal berjalan
     */
    public function bersihkan(string $path, JenisBerkas $jenis, string $mime): bool;
}
