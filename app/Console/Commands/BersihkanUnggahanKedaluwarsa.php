<?php

namespace App\Console\Commands;

use App\Services\Lampiran\UnggahanBertahap;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Menghapus unggahan lampiran yang tidak pernah dilampirkan ke laporan, setelah masa berlakunya habis.
 */
#[Signature('sigap:bersihkan-unggahan')]
#[Description('Menghapus unggahan lampiran yang kedaluwarsa dan tidak dipakai')]
class BersihkanUnggahanKedaluwarsa extends Command
{
    public function handle(UnggahanBertahap $unggahan): int
    {
        $dihapus = $unggahan->bersihkanKedaluwarsa();

        $this->info("Unggahan kedaluwarsa dihapus: {$dihapus}.");

        return self::SUCCESS;
    }
}
