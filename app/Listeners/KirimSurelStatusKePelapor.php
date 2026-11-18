<?php

namespace App\Listeners;

use App\Enums\StatusLaporan;
use App\Events\StatusLaporanBerubah;
use App\Mail\KonfirmasiPengaduan;
use App\Mail\StatusPengaduanBerubah;
use Illuminate\Support\Facades\Mail;

/**
 * Surel ke pelapor pengaduan (F-12): konfirmasi kode lacak saat laporan diterima, lalu setiap perubahan status
 * oleh petugas atau sistem. Hanya bila pelapor memberi surel (siswa: kontak orang tua/wali). Laporan WBS tidak
 * pernah dikirimi surel dari sini, dan perubahan oleh pelapor sendiri tidak memicu surel. Surel dikirim lewat
 * queue setelah transaksi tersimpan.
 */
class KirimSurelStatusKePelapor
{
    public function handle(StatusLaporanBerubah $event): void
    {
        $laporan = $event->laporan;

        if ($laporan->isWbs() || ! $event->beritahuPelapor) {
            return;
        }

        $alamat = $laporan->identitasPelapor?->alamatSurel();

        if ($alamat === null) {
            return;
        }

        $diterima = $event->riwayat->status_lama === null && $event->riwayat->status_baru === StatusLaporan::Diterima;

        Mail::to($alamat)->send($diterima
            ? new KonfirmasiPengaduan($laporan)
            : new StatusPengaduanBerubah($laporan, $event->riwayat));
    }
}
