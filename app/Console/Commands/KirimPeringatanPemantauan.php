<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Mengirim peringatan pemantauan ke surel pengelola TI (SIGAP_SUREL_PENGELOLA_TI). Dipanggil deploy/pantau/
 * cek-kesehatan.sh; surel dikirim langsung, tidak lewat antrean, karena antrean bisa jadi yang sedang berhenti.
 */
#[Signature('sigap:peringatan-pemantauan {pesan : Isi peringatan, tanpa data laporan}')]
#[Description('Mengirim peringatan pemantauan ke surel pengelola TI')]
class KirimPeringatanPemantauan extends Command
{
    public function handle(): int
    {
        $penerima = config('sigap.pemantauan.surel_pengelola_ti');

        if ($penerima === []) {
            $this->error('SIGAP_SUREL_PENGELOLA_TI belum diisi.');

            return self::FAILURE;
        }

        $pesan = (string) $this->argument('pesan');

        Mail::raw($pesan, fn (Message $surel) => $surel->to($penerima)->subject('[SIGAP] Peringatan pemantauan server'));

        $this->info('Peringatan dikirim.');

        return self::SUCCESS;
    }
}
