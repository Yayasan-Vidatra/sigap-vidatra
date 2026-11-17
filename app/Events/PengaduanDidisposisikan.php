<?php

namespace App\Events;

use App\Models\Disposisi;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pengaduan didisposisikan ke unit dan PIC (UC-09 langkah 2). Hari 7 menambahkan listener surel ke PIC
 * (US-11, F-12). Dikirim setelah transaksi tersimpan.
 */
class PengaduanDidisposisikan implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Disposisi $disposisi) {}
}
