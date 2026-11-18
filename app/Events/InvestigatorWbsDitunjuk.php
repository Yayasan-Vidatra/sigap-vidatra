<?php

namespace App\Events;

use App\Models\Laporan;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Investigator ditunjuk untuk laporan WBS (UC-13 langkah 1). Dikirim setelah transaksi tersimpan.
 */
class InvestigatorWbsDitunjuk implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Laporan $laporan, public User $investigator) {}
}
