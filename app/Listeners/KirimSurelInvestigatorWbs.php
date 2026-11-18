<?php

namespace App\Listeners;

use App\Events\InvestigatorWbsDitunjuk;
use App\Filament\Resources\LaporanWbs\LaporanWbsResource;
use App\Mail\PemberitahuanWbs;
use Illuminate\Support\Facades\Mail;

/**
 * Surel tanpa isi ke investigator yang ditunjuk (UC-13 langkah 1).
 */
class KirimSurelInvestigatorWbs
{
    public function handle(InvestigatorWbsDitunjuk $event): void
    {
        if ($event->investigator->terkunci() || blank($event->investigator->email)) {
            return;
        }

        Mail::to($event->investigator)->send(new PemberitahuanWbs(
            PemberitahuanWbs::DITUGASKAN,
            LaporanWbsResource::getUrl('view', ['record' => $event->laporan], panel: 'panel'),
        ));
    }
}
