<?php

namespace App\Exceptions;

use App\Enums\JenisLaporan;
use App\Enums\StatusLaporan;
use LogicException;

/**
 * Dilempar bila kode mencoba menyimpan data yang melanggar aturan Rancangan r1.
 */
class PelanggaranIntegritasData extends LogicException
{
    public static function jenisLaporanTidakBolehBerubah(): self
    {
        return new self('Jenis laporan tidak dapat diubah. Pindah ke WBS harus membuat laporan baru (UC-09 1a).');
    }

    public static function statusTidakBerlaku(StatusLaporan $status, JenisLaporan $jenis): self
    {
        return new self("Status \"{$status->getLabel()}\" tidak berlaku untuk {$jenis->getLabel()} (Rancangan r1 Bagian 6).");
    }

    public static function jenisBelumDiisi(string $kolom): self
    {
        return new self("Kolom jenis harus tersedia sebelum {$kolom} diisi atau dibaca, karena enkripsinya bergantung pada jenis laporan.");
    }

    public static function hanyaUntukJenis(string $entitas, JenisLaporan $jenis): self
    {
        return new self("{$entitas} hanya boleh dibuat untuk laporan {$jenis->getLabel()}.");
    }

    public static function logAuditHanyaBisaDitambah(): self
    {
        return new self('Log audit hanya bisa ditambah, tidak bisa diubah atau dihapus (NF-07).');
    }
}
