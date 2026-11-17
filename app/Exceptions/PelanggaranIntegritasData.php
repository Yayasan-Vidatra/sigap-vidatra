<?php

namespace App\Exceptions;

use App\Enums\JenisLaporan;
use App\Enums\Peran;
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

    public static function penilaianDiLuarRentang(int $penilaian): self
    {
        return new self("Penilaian harus 1 sampai 5, bukan {$penilaian} (US-13).");
    }

    public static function kepalaSekolahTerlapor(): self
    {
        return new self('Laporan tidak dapat dialihkan ke Kepala Sekolah yang menjadi terlapor; rujuk ke Pokja BSAN (US-20).');
    }

    public static function unitTujuanWajib(): self
    {
        return new self('Pengalihan ke Kepala Sekolah wajib menyebut unit tujuan (UC-19).');
    }

    public static function slaKategoriTidakSesuai(JenisLaporan $jenis): self
    {
        return $jenis === JenisLaporan::Pengaduan
            ? new self('Kategori pengaduan wajib memiliki SLA tanggapan awal dan selesai (hari kerja).')
            : new self('Kategori WBS tidak memiliki SLA; tenggat WBS diatur per tahap di config/sigap.php.');
    }

    public static function unitTidakSesuaiPeran(Peran $peran): self
    {
        return $peran->wajibUnit()
            ? new self("Peran {$peran->getLabel()} wajib terikat pada satu unit.")
            : new self("Peran {$peran->getLabel()} tidak terikat pada unit.");
    }

    public static function logAuditHanyaBisaDitambah(): self
    {
        return new self('Log audit hanya bisa ditambah, tidak bisa diubah atau dihapus (NF-07).');
    }
}
