<?php

namespace App\Services\Lampiran;

use App\Enums\JenisBerkas;
use App\Enums\JenisLaporan;
use finfo;
use ZipArchive;

/**
 * Memeriksa berkas lampiran dari isinya (Rancangan r1 Bagian 8): MIME dideteksi dengan finfo, bukan
 * dari ekstensi atau klaim browser; batas ukuran per jenis; dan makro di DOCX/XLSX ditolak.
 */
class PemeriksaBerkas
{
    /**
     * Bagian OOXML yang menandakan makro VBA atau dokumen berformat macro-enabled.
     */
    private const TANDA_MAKRO = ['vbaProject.bin', 'vbaData.xml', 'macroEnabled'];

    /**
     * @return array{jenis: JenisBerkas, mime: string, ukuran: int}
     *
     * @throws BerkasDitolak
     */
    public function periksa(string $path, string $namaAsli, JenisLaporan $kanal): array
    {
        $ukuran = (int) filesize($path);

        if ($ukuran === 0) {
            throw BerkasDitolak::kosong($namaAsli);
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $jenis = JenisBerkas::dariMime($mime);

        if ($jenis === null) {
            throw BerkasDitolak::formatTidakDiterima($namaAsli);
        }

        if (! $jenis->bolehUntuk($kanal)) {
            throw BerkasDitolak::tidakUntukKanal($namaAsli, strtolower($jenis->getLabel()));
        }

        if ($ukuran > $jenis->maksByte()) {
            throw BerkasDitolak::terlaluBesar($namaAsli, strtolower($jenis->getLabel()), intdiv($jenis->maksByte(), 1024 * 1024));
        }

        if (str_starts_with($mime, 'application/vnd.openxmlformats-officedocument') && $this->mengandungMakro($path)) {
            throw BerkasDitolak::mengandungMakro($namaAsli);
        }

        return ['jenis' => $jenis, 'mime' => $mime, 'ukuran' => $ukuran];
    }

    /**
     * DOCX/XLSX adalah arsip ZIP. Makro ada sebagai vbaProject.bin, atau dokumennya bertipe macroEnabled
     * di [Content_Types].xml. Arsip yang tidak bisa dibuka dianggap mencurigakan.
     */
    private function mengandungMakro(string $path): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return true;
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nama = (string) $zip->getNameIndex($i);

                foreach (self::TANDA_MAKRO as $tanda) {
                    if (str_contains($nama, $tanda)) {
                        return true;
                    }
                }
            }

            $jenisKonten = (string) $zip->getFromName('[Content_Types].xml');

            return stripos($jenisKonten, 'macroEnabled') !== false || stripos($jenisKonten, 'vbaProject') !== false;
        } finally {
            $zip->close();
        }
    }
}
