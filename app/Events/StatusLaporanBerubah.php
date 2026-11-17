<?php

namespace App\Events;

use App\Models\Laporan;
use App\Models\RiwayatStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Status laporan yang terlihat pelapor berubah oleh petugas (Rancangan r1 Bagian 6). Hari 7 menambahkan
 * listener surel ke pelapor bila ia memberi surel (US-10, F-12). Dikirim setelah transaksi tersimpan.
 */
class StatusLaporanBerubah implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Laporan $laporan, public RiwayatStatus $riwayat) {}
}
