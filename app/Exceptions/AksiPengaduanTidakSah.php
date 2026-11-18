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

    public static function sudahDidisposisikan(): self
    {
        return new self('Pengaduan yang sudah didisposisikan ke unit tidak dapat ditolak.');
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

    public static function catatanPenutupanWajib(): self
    {
        return new self('Catatan penutupan wajib diisi, misalnya kapan dan dengan siapa konfirmasi lewat telepon dilakukan.');
    }

    public static function tindakLanjutKosong(): self
    {
        return new self('Isi tindak lanjut terlebih dahulu sebelum meminta konfirmasi pelapor.');
    }

    public static function ringkasanPelaporWajib(): self
    {
        return new self('Ringkasan untuk pelapor wajib diisi.');
    }

    public static function ringkasanPelaporTerlaluPanjang(int $maks): self
    {
        return new self("Ringkasan untuk pelapor paling panjang {$maks} karakter.");
    }

    public static function pesanKosong(): self
    {
        return new self('Isi pesan wajib diisi.');
    }

    public static function penilaianTidakTersedia(): self
    {
        return new self('Penilaian hanya bisa diberikan sekali, setelah pengaduan selesai.');
    }

    public static function penilaianDiLuarRentang(): self
    {
        return new self('Pilih penilaian 1 sampai 5.');
    }

    public static function bukanDisposisiUnit(): self
    {
        return new self('Pengaduan ini tidak didisposisikan ke unit Anda.');
    }

    public static function alasanTolakHasilWajib(): self
    {
        return new self('Alasan menolak hasil wajib diisi.');
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
