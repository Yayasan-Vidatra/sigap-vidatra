<?php

namespace App\Jobs;

use App\Services\Pemantauan\PemeriksaKesehatan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Detak antrean (NF-04): hanya tercatat bila worker queue:work benar-benar memproses job.
 */
class CatatDetakAntrean implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        PemeriksaKesehatan::catatDetak(PemeriksaKesehatan::KUNCI_ANTREAN);
    }
}
