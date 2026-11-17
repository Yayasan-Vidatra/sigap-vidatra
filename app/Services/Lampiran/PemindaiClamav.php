<?php

namespace App\Services\Lampiran;

use App\Enums\StatusPindai;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Driver "alat": ClamAV lewat clamscan. Kode keluar 0 = bersih, 1 = terinfeksi, lainnya = galat.
 */
class PemindaiClamav implements PemindaiVirus
{
    public function pindai(string $path): array
    {
        $hasil = Process::timeout(300)->run([
            (string) config('sigap.lampiran.alat.clamscan'),
            '--no-summary',
            '--infected',
            $path,
        ]);

        return match ($hasil->exitCode()) {
            0 => ['status' => StatusPindai::Bersih, 'keterangan' => null],
            1 => ['status' => StatusPindai::Karantina, 'keterangan' => $this->namaAncaman($hasil->output())],
            default => throw new RuntimeException('clamscan gagal berjalan (kode '.$hasil->exitCode().'): '.trim($hasil->errorOutput())),
        };
    }

    private function namaAncaman(string $keluaran): string
    {
        return preg_match('/:\s*(.+?)\s+FOUND/', $keluaran, $cocok) === 1 ? $cocok[1] : 'terdeteksi berbahaya';
    }
}
