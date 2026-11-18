<?php

namespace App\Events;

use App\Models\Laporan;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Laporan WBS perlu ditelaah oleh penelaah menurut matriks (F-19): laporan baru dari laman atau dicatat dari
 * saluran lain, atau perutean berpindah karena kedudukan terlapor dikoreksi. Dikirim setelah transaksi tersimpan.
 */
class LaporanWbsPerluDitelaah implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Laporan $laporan, public bool $dialihkan = false) {}
}
