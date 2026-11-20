<?php

namespace App\Mail;

use App\Models\Laporan;
use App\Models\RiwayatStatus;
use App\Services\WaktuLokal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Surel ke pelapor saat status pengaduannya berubah (F-12, US-10). Isinya sengaja minimal: kode lacak,
 * status, waktu dalam WITA, dan catatan hanya bila bertanda tampil_ke_pelapor. Tanpa kronologi, identitas,
 * nama petugas, atau catatan internal.
 */
class StatusPengaduanBerubah extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Laporan $laporan, public RiwayatStatus $riwayat)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Status pengaduan {$this->laporan->kode_lacak}: {$this->riwayat->status_baru->labelUntukPelapor()}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.status-pengaduan-berubah',
            with: [
                'kodeLacak' => $this->laporan->kode_lacak,
                'status' => $this->riwayat->status_baru->labelUntukPelapor(),
                'waktu' => WaktuLokal::tampilkan($this->riwayat->waktu, 'd F Y, H.i'),
                'catatan' => $this->riwayat->tampil_ke_pelapor ? $this->riwayat->catatan : null,
                'penjelasan' => $this->riwayat->status_baru->penjelasanUntukPelapor(),
                'tautanLacak' => route('lacak'),
            ],
        );
    }
}
