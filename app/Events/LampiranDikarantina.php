<?php

namespace App\Events;

use App\Models\Lampiran;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lampiran terdeteksi berbahaya dan dikarantina (UC-01 alur 5a). Hari 7 menambahkan surel pemberitahuan.
 */
class LampiranDikarantina
{
    use Dispatchable, SerializesModels;

    public function __construct(public Lampiran $lampiran, public string $keterangan) {}
}
