<?php

namespace App\Mail;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pemberitahuan otomatis kepada pemegang peran Tim WBS, penerima hasil, atau Super Admin saat perannya diberikan,
 * dicabut, atau 2FA-nya diatur ulang (Rancangan r1 Bagian 9). Tanpa isi laporan apa pun.
 */
class PemberitahuanPeranKhusus extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public AksiAudit $aksi, public ?Peran $peran, public string $dasar, public string $tautan)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->aksi) {
            AksiAudit::MencabutPeranKhusus => 'Peran Anda di SIGAP Vidatra dicabut',
            AksiAudit::MengaturUlang2fa => '2FA akun SIGAP Vidatra Anda diatur ulang',
            default => 'Anda diberi peran di SIGAP Vidatra',
        });
    }

    public function content(): Content
    {
        $peran = $this->peran?->getLabel() ?? '-';

        return new Content(
            markdown: 'mail.pemberitahuan-peran-khusus',
            with: [
                'kalimat' => match ($this->aksi) {
                    AksiAudit::MencabutPeranKhusus => "Peran {$peran} pada akun Anda telah dicabut. Anda tidak dapat lagi membuka panel dengan peran tersebut.",
                    AksiAudit::MengaturUlang2fa => "2FA akun Anda (peran {$peran}) telah diatur ulang. Pasang kembali aplikasi autentikator saat login berikutnya.",
                    default => "Akun Anda kini memegang peran {$peran}. Login dan pasang 2FA sebelum memakai panel.",
                },
                'dasar' => $this->dasar,
                'tautan' => $this->tautan,
            ],
        );
    }
}
