<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pemberitahuan pengalihan (UC-19, F-12): ke Kepala Sekolah/Komite Etik bahwa ada laporan dialihkan kepadanya, atau ke
 * Ketua Umum dan Dewan Pengawas bahwa laporan dirujuk ke Pokja BSAN. Tanpa kode lacak, ringkasan, nama terlapor, atau
 * identitas pelapor.
 */
class PemberitahuanPengalihan extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public bool $rujukanPokja, public ?string $tautan)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->rujukanPokja ? 'Laporan dirujuk ke Pokja BSAN' : 'Ada laporan dialihkan kepada Anda');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.pemberitahuan-pengalihan', with: [
            'rujukanPokja' => $this->rujukanPokja,
            'tautan' => $this->tautan,
        ]);
    }
}
