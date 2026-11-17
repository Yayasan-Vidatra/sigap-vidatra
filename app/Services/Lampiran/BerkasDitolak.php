<?php

namespace App\Services\Lampiran;

use RuntimeException;

/**
 * Berkas lampiran tidak memenuhi Rancangan r1 Bagian 8. Pesannya berbahasa Indonesia dan aman
 * ditampilkan ke pelapor.
 */
class BerkasDitolak extends RuntimeException
{
    public static function formatTidakDiterima(string $namaAsli): self
    {
        return new self("\"{$namaAsli}\" ditolak: format berkas tidak diterima. Gunakan PDF, DOCX, XLSX, TXT, JPG, PNG, HEIC, MP4, atau MOV.");
    }

    public static function tidakUntukKanal(string $namaAsli, string $jenis): self
    {
        return new self("\"{$namaAsli}\" ditolak: lampiran {$jenis} tidak diterima di kanal ini.");
    }

    public static function terlaluBesar(string $namaAsli, string $jenis, int $maksMb): self
    {
        return new self("\"{$namaAsli}\" ditolak: ukuran {$jenis} paling besar {$maksMb} MB.");
    }

    public static function mengandungMakro(string $namaAsli): self
    {
        return new self("\"{$namaAsli}\" ditolak: dokumen berisi makro tidak diterima. Simpan ulang sebagai DOCX/XLSX biasa atau PDF.");
    }

    public static function kosong(string $namaAsli): self
    {
        return new self("\"{$namaAsli}\" ditolak: berkas kosong.");
    }
}
