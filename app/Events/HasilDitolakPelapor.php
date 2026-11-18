<?php

namespace App\Events;

use App\Models\Laporan;
use App\Models\RiwayatStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pelapor menolak hasil tindak lanjut, sehingga pengaduan kembali Diproses (Rancangan r1 Bagian 6). Admin
 * Pengaduan diberi tahu lewat surel.
 */
class HasilDitolakPelapor implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Laporan $laporan, public RiwayatStatus $riwayat) {}
}
