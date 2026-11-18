<?php

namespace App\Events;

use App\Models\Laporan;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tim WBS mengajukan perpanjangan investigasi kepada penerima hasil (UC-13 1a). Dikirim setelah
 * transaksi tersimpan.
 */
class PerpanjanganWbsDiajukan implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Laporan $laporan) {}
}
