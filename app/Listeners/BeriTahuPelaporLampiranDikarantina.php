<?php

namespace App\Listeners;

use App\Enums\AksiAudit;
use App\Enums\PengirimPesan;
use App\Events\LampiranDikarantina;
use App\Models\LogAudit;

/**
 * Memberi tahu pelapor lewat kanal pesan laporan (tampil di halaman Lacak, H-07) dan mencatat karantina
 * di log audit. Nama ancaman hanya dicatat untuk petugas, tidak ditampilkan ke pelapor.
 */
class BeriTahuPelaporLampiranDikarantina
{
    public function handle(LampiranDikarantina $event): void
    {
        $lampiran = $event->lampiran;

        $lampiran->laporan->pesan()->create([
            'pengirim' => PengirimPesan::Sistem,
            'internal' => false,
            'isi' => "Lampiran \"{$lampiran->nama_asli}\" tidak dapat diterima karena terdeteksi berbahaya dan sudah "
                .'dikarantina. Laporan Anda tetap tersimpan. Bila perlu, kirim bukti pengganti lewat pesan.',
        ]);

        LogAudit::catat(null, AksiAudit::LampiranDikarantina, $lampiran);
    }
}
