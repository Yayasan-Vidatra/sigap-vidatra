<?php

namespace App\Events;

use App\Models\Laporan;
use App\Models\RiwayatStatus;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Status laporan yang terlihat pelapor berubah (Rancangan r1 Bagian 6), termasuk saat laporan diterima.
 * Listener mengirim surel ke pelapor bila ia memberi surel (US-10, F-12). Dikirim setelah transaksi tersimpan.
 */
class StatusLaporanBerubah implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  bool  $beritahuPelapor  false bila pelapor sendiri yang mengubah status (konfirmasi atau tolak hasil)
     */
    public function __construct(
        public Laporan $laporan,
        public RiwayatStatus $riwayat,
        public bool $beritahuPelapor = true,
    ) {}
}
