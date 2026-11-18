<?php

namespace App\Exceptions;

/**
 * Dilempar bila pengalihan laporan (UC-19, H-11, H-13, H-17) tidak sesuai aturan. Pesannya ditampilkan ke petugas.
 */
class AksiPengalihanTidakSah extends AksiPengaduanTidakSah
{
    public static function kepalaSekolahTerlibat(bool $wbs): self
    {
        return new self($wbs
            ? 'Kepala Sekolah unit ini termasuk yang dilaporkan, sehingga laporan tidak dapat dialihkan kepadanya. Rujuk ke Pokja BSAN.'
            : 'Kepala Sekolah unit ini termasuk yang dilaporkan, sehingga pengaduan tidak dapat dialihkan kepadanya. Sampaikan kepada Tim WBS agar dirujuk ke Pokja BSAN.');
    }

    public static function tidakAdaPenerima(): self
    {
        return new self('Belum ada akun penerima yang aktif untuk tujuan ini. Minta Super Admin menambahkannya di Pengaturan.');
    }

    public static function tujuanTidakBerlaku(): self
    {
        return new self('Tujuan pengalihan ini tidak tersedia untuk laporan ini.');
    }

    public static function ringkasanWajib(): self
    {
        return new self('Ringkasan pengalihan wajib diisi.');
    }

    public static function sudahDikonfirmasi(): self
    {
        return new self('Penerimaan pengalihan ini sudah dikonfirmasi.');
    }

    public static function belumDikonfirmasi(): self
    {
        return new self('Konfirmasi terima pengalihan terlebih dahulu.');
    }
}
