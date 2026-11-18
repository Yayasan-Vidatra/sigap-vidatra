<?php

namespace App\Mail;

use App\Models\Laporan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pemberitahuan ada pesan baru di sebuah laporan (UC-05, F-12). Isi pesan, nama pengirim, dan lampiran
 * sengaja tidak disertakan; penerima membacanya di panel (petugas) atau di halaman Lacak (pelapor).
 */
class PesanBaru extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  bool  $untukPetugas  true untuk Admin Pengaduan, false untuk pelapor
     */
    public function __construct(public Laporan $laporan, public bool $untukPetugas, public string $tautan)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Ada pesan baru untuk laporan {$this->laporan->kode_lacak}");
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pesan-baru',
            with: [
                'kodeLacak' => $this->laporan->kode_lacak,
                'untukPetugas' => $this->untukPetugas,
                'tautan' => $this->tautan,
            ],
        );
    }
}
