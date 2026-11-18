<?php

namespace App\Events;

use App\Models\Laporan;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Kesimpulan dan rekomendasi WBS dikirim kepada penerima hasil menurut matriks (UC-13 langkah 5). Dikirim setelah
 * transaksi tersimpan.
 */
class HasilWbsDikirim implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Laporan $laporan) {}
}
