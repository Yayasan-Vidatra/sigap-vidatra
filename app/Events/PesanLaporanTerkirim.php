<?php

namespace App\Events;

use App\Models\Pesan;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pesan baru di kanal pesan laporan (UC-05), dari pelapor atau petugas. Listener mengirim surel
 * pemberitahuan tanpa isi pesan. Dikirim setelah transaksi tersimpan.
 */
class PesanLaporanTerkirim implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Pesan $pesan) {}
}
