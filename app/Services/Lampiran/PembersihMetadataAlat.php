<?php

namespace App\Services\Lampiran;

use App\Enums\JenisBerkas;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Driver "alat": ExifTool untuk foto dan PDF, FFmpeg untuk video dan audio. DOCX/XLSX/TXT tidak
 * dibersihkan karena ExifTool tidak bisa menulis metadata OOXML; konversi HEIC ke JPG dan thumbnail
 * belum termasuk.
 */
class PembersihMetadataAlat implements PembersihMetadata
{
    /**
     * Ekstensi untuk berkas keluaran FFmpeg, agar format kontainernya tetap.
     */
    private const EKSTENSI_FFMPEG = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
    ];

    public function bersihkan(string $path, JenisBerkas $jenis, string $mime): bool
    {
        if ($jenis === JenisBerkas::Foto || $mime === 'application/pdf') {
            $this->jalankan([(string) config('sigap.lampiran.alat.exiftool'), '-all=', '-overwrite_original', $path]);

            return true;
        }

        if (isset(self::EKSTENSI_FFMPEG[$mime])) {
            $keluaran = $path.'.bersih.'.self::EKSTENSI_FFMPEG[$mime];
            $this->jalankan([
                (string) config('sigap.lampiran.alat.ffmpeg'), '-y', '-v', 'error', '-i', $path,
                '-map', '0', '-map_metadata', '-1', '-map_chapters', '-1', '-c', 'copy', $keluaran,
            ]);

            PenyimpananLampiran::timpa($keluaran, $path);

            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $perintah
     */
    private function jalankan(array $perintah): void
    {
        $hasil = Process::timeout(600)->run($perintah);

        if ($hasil->failed()) {
            throw new RuntimeException(basename($perintah[0]).' gagal membersihkan metadata: '.trim($hasil->errorOutput()));
        }
    }
}
