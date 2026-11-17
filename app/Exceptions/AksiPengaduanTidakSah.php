<?php

namespace App\Exceptions;

use App\Enums\StatusLaporan;
use RuntimeException;

/**
 * Dilempar bila aksi petugas pada pengaduan tidak sesuai alur Rancangan r1 Bagian 6 atau isiannya
 * tidak sah, misalnya pengaduan yang sudah diproses diverifikasi ulang karena dua admin membuka
 * halaman yang sama. Pesannya ditampilkan kepada petugas.
 */
class AksiPengaduanTidakSah extends RuntimeException
{
    public static function statusTidakSesuai(string $aksi, StatusLaporan $status): self
    {
        return new self("Pengaduan berstatus \"{$status->getLabel()}\" tidak dapat {$aksi}. Muat ulang halaman untuk melihat status terbaru.");
    }

    public static function kategoriBukanPengaduan(): self
    {
        return new self('Kategori yang dipilih bukan kategori pengaduan.');
    }

    public static function kategoriBelumDitetapkan(): self
    {
        return new self('Pengaduan belum memiliki kategori, sehingga batas waktu tidak dapat dihitung. Verifikasi dan kategorikan terlebih dahulu.');
    }

    public static function alasanWajib(): self
    {
        return new self('Alasan penolakan wajib diisi.');
    }

    public static function unitTidakSah(): self
    {
        return new self('Unit tujuan tidak aktif atau di luar unit yang Anda tangani.');
    }

    public static function picTidakSah(): self
    {
        return new self('PIC yang dipilih bukan PIC Unit aktif pada unit tujuan.');
    }
}
