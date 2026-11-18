<?php

namespace App\Exceptions;

use App\Enums\StatusLaporan;

/**
 * Dilempar bila aksi di Ruang Kerja WBS (H-13) tidak sesuai alur Rancangan r1 Bagian 6 atau isiannya tidak sah.
 * Turunan AksiPengaduanTidakSah agar langkah bersama PengubahStatus dapat dipakai. Pesannya ditampilkan ke petugas.
 */
class AksiWbsTidakSah extends AksiPengaduanTidakSah
{
    public static function statusWbsTidakSesuai(string $aksi, StatusLaporan $status): self
    {
        return new self("Laporan berstatus \"{$status->getLabel()}\" tidak dapat {$aksi}. Muat ulang halaman untuk melihat status terbaru.");
    }

    public static function isianWajib(string $isian): self
    {
        return new self("{$isian} wajib diisi.");
    }

    public static function investigatorTidakSah(): self
    {
        return new self('Investigator yang dipilih tidak boleh menangani laporan ini (terkunci, disebut sebagai terlapor, atau tertutup menurut matriks).');
    }

    public static function kedudukanSama(): self
    {
        return new self('Kedudukan terlapor yang dipilih sama dengan yang tercatat.');
    }
}
