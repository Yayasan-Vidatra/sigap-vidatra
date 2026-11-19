<?php

namespace App\Console\Commands;

use App\Jobs\CatatDetakAntrean;
use App\Services\Pemantauan\PemeriksaKesehatan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Dijalankan scheduler setiap menit: mencatat detak scheduler dan mengirim job detak ke antrean (NF-04).
 */
#[Signature('sigap:detak')]
#[Description('Mencatat detak scheduler dan antrean untuk pemeriksaan kesehatan')]
class CatatDetak extends Command
{
    public function handle(): int
    {
        PemeriksaKesehatan::catatDetak(PemeriksaKesehatan::KUNCI_SCHEDULER);
        CatatDetakAntrean::dispatch();

        return self::SUCCESS;
    }
}
