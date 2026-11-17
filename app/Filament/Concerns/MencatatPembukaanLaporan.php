<?php

namespace App\Filament\Concerns;

use App\Enums\AksiAudit;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Mencatat setiap pembukaan halaman laporan di log audit (US-27, F-16). Pasang pada halaman record
 * laporan Filament (H-11, H-12, H-13, H-16, H-17).
 *
 * Livewire memanggil hook ini satu kali saat halaman dibuka, setelah mount() halaman, yaitu setelah
 * record dimuat dan aksesnya diotorisasi. Interaksi berikutnya di halaman yang sama tidak dicatat ulang.
 */
trait MencatatPembukaanLaporan
{
    abstract public function getRecord(): Model;

    public function mountMencatatPembukaanLaporan(): void
    {
        $laporan = $this->getRecord();

        if (! $laporan instanceof Laporan) {
            return;
        }

        $user = Filament::auth()->user();

        LogAudit::catat($user instanceof User ? $user : null, AksiAudit::MembukaLaporan, $laporan);
    }
}
