<?php

namespace App\Listeners;

use App\Events\HasilWbsDikirim;
use App\Events\PerpanjanganWbsDiajukan;
use App\Filament\Resources\HasilWbs\HasilWbsResource;
use App\Mail\PemberitahuanWbs;
use App\Models\Laporan;
use App\Models\User;
use App\Services\Wbs\AksesWbs;
use Illuminate\Support\Facades\Mail;

/**
 * Surel tanpa isi ke penerima hasil menurut matriks (F-19, US-21): hasil (beserta tembusan Ketua Umum untuk terlapor
 * guru/pegawai/Kepala Sekolah) dan permintaan persetujuan perpanjangan (penerima utama saja).
 */
class KirimSurelPenerimaHasilWbs
{
    public function handleHasil(HasilWbsDikirim $event): void
    {
        $this->kirim($event->laporan, PemberitahuanWbs::HASIL, denganTembusan: true);
    }

    public function handlePerpanjangan(PerpanjanganWbsDiajukan $event): void
    {
        $this->kirim($event->laporan, PemberitahuanWbs::PERPANJANGAN, denganTembusan: false);
    }

    private function kirim(Laporan $laporan, string $jenis, bool $denganTembusan): void
    {
        $laporan = $laporan->fresh(['terlapor']);

        if ($laporan === null || ! $laporan->isWbs()) {
            return;
        }

        $tautan = HasilWbsResource::getUrl('view', ['record' => $laporan], panel: 'panel');

        AksesWbs::penerimaHasilAktif($laporan, $denganTembusan)
            ->filter(fn (User $user): bool => filled($user->email))
            ->each(fn (User $user) => Mail::to($user)->send(new PemberitahuanWbs($jenis, $tautan)));
    }
}
