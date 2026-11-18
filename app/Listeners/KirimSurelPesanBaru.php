<?php

namespace App\Listeners;

use App\Enums\PengirimPesan;
use App\Events\PesanLaporanTerkirim;
use App\Filament\Resources\Pengaduan\PengaduanResource;
use App\Mail\PesanBaru;
use App\Services\Pengaduan\PenerimaNotifikasi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Surel "ada pesan baru" tanpa isi pesan (UC-05, F-12), hanya untuk pengaduan:
 * - pesan pelapor: ke Admin Pengaduan yayasan dan Admin Sekolah unit itu;
 * - pesan petugas yang bukan catatan internal: ke pelapor (siswa: kontak orang tua/wali).
 * Paling banyak satu surel per laporan per arah dalam 30 menit, agar rangkaian pesan tidak membanjiri kotak surel.
 */
class KirimSurelPesanBaru
{
    public const JEDA_MENIT = 30;

    public function handle(PesanLaporanTerkirim $event): void
    {
        $pesan = $event->pesan;
        $laporan = $pesan->laporan;

        if ($laporan->isWbs() || $pesan->internal || $pesan->pengirim === PengirimPesan::Sistem) {
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
}
