<?php

namespace App\Listeners;

use App\Enums\PengirimPesan;
use App\Events\PesanLaporanTerkirim;
use App\Filament\Resources\LaporanWbs\LaporanWbsResource;
use App\Filament\Resources\Pengaduan\PengaduanResource;
use App\Mail\PemberitahuanWbs;
use App\Mail\PesanBaru;
use App\Models\Laporan;
use App\Models\Pesan;
use App\Models\User;
use App\Services\Pengaduan\PenerimaNotifikasi;
use App\Services\Wbs\AksesWbs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Surel "ada pesan baru" tanpa isi pesan (UC-05, F-12). Pengaduan:
 * - pesan pelapor: ke Admin Pengaduan yayasan dan Admin Sekolah unit itu;
 * - pesan petugas yang bukan catatan internal: ke pelapor (siswa: kontak orang tua/wali).
 * WBS: pesan pelapor ke penelaah utama dan investigator, tanpa kode lacak (PemberitahuanWbs).
 * Paling banyak satu surel per laporan per arah dalam 30 menit, agar rangkaian pesan tidak membanjiri kotak surel.
 */
class KirimSurelPesanBaru
{
    public const JEDA_MENIT = 30;

    public function handle(PesanLaporanTerkirim $event): void
    {
        $pesan = $event->pesan;
        $laporan = $pesan->laporan;

        if ($pesan->internal || $pesan->pengirim === PengirimPesan::Sistem) {
            return;
        }

        if ($laporan->isWbs()) {
            $this->beritahuPenanganWbs($laporan, $pesan);

            return;
        }

        $untukPetugas = $pesan->pengirim === PengirimPesan::Pelapor;

        if (! Cache::add("surel-pesan:{$laporan->getKey()}:".($untukPetugas ? 'petugas' : 'pelapor'), true, now()->addMinutes(self::JEDA_MENIT))) {
            return;
        }

        if ($untukPetugas) {
            $tautan = PengaduanResource::getUrl('view', ['record' => $laporan], panel: 'panel');

            foreach (PenerimaNotifikasi::adminUntuk($laporan) as $admin) {
                Mail::to($admin)->send(new PesanBaru($laporan, untukPetugas: true, tautan: $tautan));
            }

            return;
        }

        $alamat = $laporan->identitasPelapor?->alamatSurel();

        if ($alamat !== null) {
            Mail::to($alamat)->send(new PesanBaru($laporan, untukPetugas: false, tautan: route('lacak')));
        }
    }

    /**
     * WBS: pesan pelapor diberitahukan tanpa isi kepada penelaah utama dan investigator. Pelapor WBS tidak pernah
     * dikirimi surel; balasan Tim WBS dibaca di halaman Lacak.
     */
    private function beritahuPenanganWbs(Laporan $laporan, Pesan $pesan): void
    {
        if ($pesan->pengirim !== PengirimPesan::Pelapor
            || ! Cache::add("surel-pesan:{$laporan->getKey()}:petugas", true, now()->addMinutes(self::JEDA_MENIT))) {
            return;
        }

        $laporan->loadMissing(['terlapor', 'investigator']);
        $penerima = AksesWbs::penelaahAktif($laporan);

        if ($laporan->investigator !== null && ! $laporan->investigator->terkunci() && AksesWbs::bolehBuka($laporan->investigator, $laporan)) {
            $penerima->push($laporan->investigator);
        }

        $tautan = LaporanWbsResource::getUrl('view', ['record' => $laporan], panel: 'panel');

        $penerima->unique('id')
            ->filter(fn (User $user): bool => filled($user->email))
            ->each(fn (User $user) => Mail::to($user)->send(new PemberitahuanWbs(PemberitahuanWbs::PESAN_BARU, $tautan)));
    }
}
