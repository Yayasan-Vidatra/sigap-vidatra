<?php

namespace App\Console\Commands;

use App\Enums\JenisLaporan;
use App\Enums\StatusLaporan;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Laporan;
use App\Services\Pengaduan\KonfirmasiPelapor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Menutup pengaduan Menunggu Konfirmasi yang tidak ditanggapi pelapor dalam 7 hari kalender WITA
 * (Rancangan r1 Bagian 6). Dijadwalkan harian setelah tengah malam WITA.
 */
#[Signature('sigap:tutup-pengaduan-otomatis')]
#[Description('Menutup pengaduan yang 7 hari tanpa tanggapan pelapor')]
class TutupPengaduanOtomatis extends Command
{
    public function handle(KonfirmasiPelapor $konfirmasi): int
    {
        $ditutup = 0;

        Laporan::query()
            ->where('jenis', JenisLaporan::Pengaduan->value)
            ->where('status', StatusLaporan::MenungguKonfirmasi->value)
            ->lazyById()
            ->each(function (Laporan $laporan) use ($konfirmasi, &$ditutup): void {
                try {
                    $ditutup += $konfirmasi->tutupOtomatis($laporan) !== null ? 1 : 0;
                } catch (AksiPengaduanTidakSah) {
                    // Status berubah sejak diambil (pelapor baru saja menanggapi); dilewati.
                }
            });

        $this->info("Pengaduan ditutup otomatis: {$ditutup}.");

        return self::SUCCESS;
    }
}
