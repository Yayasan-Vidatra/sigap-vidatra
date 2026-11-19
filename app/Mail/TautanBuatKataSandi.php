<?php

namespace App\Mail;

use App\Enums\Peran;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tautan sekali pakai untuk membuat kata sandi akun lokal (sigap:peran-khusus). Sengaja tidak diantrekan agar
 * tautan bertoken tidak tersimpan di tabel antrean. Tanpa isi laporan.
 */
class TautanBuatKataSandi extends Mailable
{
    public function __construct(public ?Peran $peran, public string $tautan, public int $berlakuMenit) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Buat kata sandi akun SIGAP Vidatra Anda');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.tautan-buat-kata-sandi', with: [
            'peran' => $this->peran?->getLabel() ?? '-',
            'tautan' => $this->tautan,
            'berlakuMenit' => $this->berlakuMenit,
        ]);
    }
}
