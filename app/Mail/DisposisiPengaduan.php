<?php

namespace App\Mail;

use App\Filament\Resources\PengaduanUnit\PengaduanUnitResource;
use App\Models\Disposisi;
use App\Services\WaktuLokal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Surel ke PIC Unit saat pengaduan didisposisikan kepadanya (UC-09 langkah 2, US-11). Kronologi dan identitas
 * pelapor tidak dikirim lewat surel; PIC membukanya di panel (H-12), yang mencatat pembukaan di log audit.
 */
class DisposisiPengaduan extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Disposisi $disposisi)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Disposisi pengaduan {$this->disposisi->laporan->kode_lacak} untuk {$this->disposisi->unit->nama}",
        );
    }

    public function content(): Content
    {
        $laporan = $this->disposisi->laporan;

        return new Content(
            markdown: 'mail.disposisi-pengaduan',
            with: [
                'kodeLacak' => $laporan->kode_lacak,
                'judul' => $laporan->judul,
                'kategori' => $laporan->kategori?->nama,
                'prioritas' => $laporan->prioritas?->getLabel(),
                'batasWaktu' => $laporan->batas_waktu ? WaktuLokal::keLokal($laporan->batas_waktu)->locale('id')->translatedFormat('l, d F Y') : null,
                'instruksi' => $this->disposisi->instruksi,
                'tautan' => PengaduanUnitResource::getUrl('view', ['record' => $laporan], panel: 'panel'),
            ],
        );
    }
}
