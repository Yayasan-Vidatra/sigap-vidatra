<?php

namespace App\Events;

use App\Models\Pengalihan;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Laporan dialihkan ke Kepala Sekolah, Komite Etik, atau dirujuk ke Pokja BSAN (UC-19). Dikirim setelah transaksi
 * tersimpan.
 */
class PengalihanDikirim implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Pengalihan $pengalihan) {}
}
