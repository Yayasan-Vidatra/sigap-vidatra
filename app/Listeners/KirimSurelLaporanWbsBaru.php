<?php

namespace App\Listeners;

use App\Events\LaporanWbsPerluDitelaah;
use App\Filament\Resources\LaporanWbs\LaporanWbsResource;
use App\Mail\PemberitahuanWbs;
use App\Models\User;
use App\Services\Wbs\AksesWbs;
use Illuminate\Support\Facades\Mail;

/**
 * Surel tanpa isi ke penelaah utama menurut matriks (F-19, UC-02 langkah 6): Ketua Tim WBS, atau penelaah pengganti
 * (Dewan Pengawas, Ketua Pembina, Pembina). Pejabat yang disebut sebagai terlapor dan akun terkunci tidak dikirimi.
 */
class KirimSurelLaporanWbsBaru
{
    public function handle(LaporanWbsPerluDitelaah $event): void
    {
        $laporan = $event->laporan->fresh(['terlapor']);

        if ($laporan === null || ! $laporan->isWbs()) {
            return;
        }

        $tautan = LaporanWbsResource::getUrl('view', ['record' => $laporan], panel: 'panel');

        AksesWbs::penelaahAktif($laporan)
            ->filter(fn (User $user): bool => filled($user->email))
            ->each(fn (User $user) => Mail::to($user)->send(new PemberitahuanWbs(
                $event->dialihkan ? PemberitahuanWbs::DIALIHKAN : PemberitahuanWbs::LAPORAN_BARU,
                $tautan,
            )));
    }
}
