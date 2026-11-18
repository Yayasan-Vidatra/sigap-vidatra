<?php

namespace App\Mail;

use App\Filament\Resources\Pengaduan\PengaduanResource;
use App\Models\Laporan;
use App\Models\RiwayatStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Surel ke Admin Pengaduan saat pelapor menolak hasil tindak lanjut, sehingga pengaduan kembali Diproses
 * (Rancangan r1 Bagian 6). Identitas pelapor dan kronologi tidak dikirim lewat surel.
 */
class HasilPengaduanDitolakPelapor extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Laporan $laporan, public RiwayatStatus $riwayat)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pelapor menolak hasil pengaduan {$this->laporan->kode_lacak}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.hasil-pengaduan-ditolak-pelapor',
            with: [
                'kodeLacak' => $this->laporan->kode_lacak,
                'unit' => $this->laporan->unit?->nama,
                'alasan' => $this->riwayat->catatan,
                'tautan' => PengaduanResource::getUrl('view', ['record' => $this->laporan], panel: 'panel'),
            ],
        );
    }
}
