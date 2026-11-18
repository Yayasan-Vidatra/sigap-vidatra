<?php

namespace App\Listeners;

use App\Events\PengaduanDidisposisikan;
use App\Mail\DisposisiPengaduan;
use Illuminate\Support\Facades\Mail;

/**
 * Surel ke PIC Unit saat pengaduan didisposisikan kepadanya (US-11: "PIC menerima surel"), lewat queue.
 */
class KirimSurelDisposisiKePic
{
    public function handle(PengaduanDidisposisikan $event): void
    {
        // Dimuat ulang: relasi laporan pada disposisi bisa masih memuat keadaan sebelum disposisi tersimpan.
        $disposisi = $event->disposisi->fresh(['laporan.kategori', 'unit', 'pic']);
        $pic = $disposisi?->pic;

        if ($pic === null || $pic->terkunci()) {
            return;
        }

        Mail::to($pic)->send(new DisposisiPengaduan($disposisi));
    }
}
