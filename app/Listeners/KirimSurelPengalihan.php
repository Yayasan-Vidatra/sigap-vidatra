<?php

namespace App\Listeners;

use App\Enums\TujuanPengalihan;
use App\Events\PengalihanDikirim;
use App\Filament\Resources\LaporanDialihkan\LaporanDialihkanResource;
use App\Mail\PemberitahuanPengalihan;
use App\Models\User;
use App\Services\Pengalihan\PengalihanLaporan;
use Illuminate\Support\Facades\Mail;

/**
 * Surel tanpa isi ke penerima pengalihan (UC-19), atau ke Ketua Umum dan Dewan Pengawas untuk rujukan Pokja BSAN.
 */
class KirimSurelPengalihan
{
    public function handle(PengalihanDikirim $event): void
    {
        $pengalihan = $event->pengalihan->fresh(['laporan.terlapor', 'unitTujuan']);

        if ($pengalihan === null) {
            return;
        }

        $pokja = $pengalihan->tujuan === TujuanPengalihan::PokjaBsan;
        $tautan = $pokja ? null : LaporanDialihkanResource::getUrl('view', ['record' => $pengalihan], panel: 'panel');

        PengalihanLaporan::penerima($pengalihan->tujuan, $pengalihan->unitTujuan, $pengalihan->laporan)
            ->filter(fn (User $user): bool => filled($user->email))
            ->each(fn (User $user) => Mail::to($user)->send(new PemberitahuanPengalihan($pokja, $tautan)));
    }
}
