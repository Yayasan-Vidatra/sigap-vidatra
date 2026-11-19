<?php

namespace App\Services\Pengaturan;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Persetujuan tertulis Ketua Dewan Pengawas untuk memberi, mencabut, atau mengatur ulang 2FA peran Tim WBS,
 * penerima hasil, dan Super Admin (Rancangan r1 Bagian 9). Nomor wajib diisi; tanggal berformat YYYY-MM-DD dan
 * tidak boleh di masa depan.
 */
final readonly class DasarPersetujuan
{
    public string $nomor;

    public CarbonImmutable $tanggal;

    public function __construct(?string $nomor, ?string $tanggal)
    {
        $nomor = trim((string) $nomor);

        if ($nomor === '') {
            throw new InvalidArgumentException('Nomor persetujuan tertulis Ketua Dewan Pengawas wajib diisi.');
        }

        $hasil = CarbonImmutable::createFromFormat('!Y-m-d', trim((string) $tanggal));

        if ($hasil === false || $hasil->format('Y-m-d') !== trim((string) $tanggal)) {
            throw new InvalidArgumentException('Tanggal persetujuan wajib diisi dengan format YYYY-MM-DD.');
        }

        if ($hasil->isFuture()) {
            throw new InvalidArgumentException('Tanggal persetujuan tidak boleh di masa depan.');
        }

        $this->nomor = mb_substr($nomor, 0, 100);
        $this->tanggal = $hasil;
    }

    public function teks(): string
    {
        return "Persetujuan Ketua Dewan Pengawas nomor {$this->nomor} tanggal {$this->tanggal->format('Y-m-d')}";
    }
}
