<?php

namespace App\Listeners;

use App\Events\HasilDitolakPelapor;
use App\Mail\HasilPengaduanDitolakPelapor;
use App\Services\Pengaduan\PenerimaNotifikasi;
use Illuminate\Support\Facades\Mail;

/**
 * Surel ke Admin Pengaduan yayasan dan Admin Sekolah unit itu saat pelapor menolak hasil (Rancangan r1
 * Bagian 6), lewat queue.
 */
class KirimSurelHasilDitolakKeAdmin
{
    public function handle(HasilDitolakPelapor $event): void
    {
        foreach (PenerimaNotifikasi::adminUntuk($event->laporan) as $admin) {
            Mail::to($admin)->send(new HasilPengaduanDitolakPelapor($event->laporan, $event->riwayat));
        }
    }
}
