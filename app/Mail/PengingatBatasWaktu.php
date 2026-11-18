<?php

namespace App\Mail;

use App\Models\Laporan;
use App\Services\WaktuLokal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pengingat dan eskalasi batas waktu pengaduan (F-11, UC-09 2a, US-11). Pengingat dikirim ke PIC saat batas
 * waktu tinggal 2 hari kerja; eskalasi dikirim ke PIC, Admin, dan Pimpinan Yayasan saat batas terlewati.
 * Tanpa kronologi dan identitas pelapor. Tautan panel hanya untuk penerima yang boleh membuka pengaduan.
 */
class PengingatBatasWaktu extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Laporan $laporan, public bool $eskalasi, public ?string $tautan)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->eskalasi
                ? "Eskalasi: pengaduan {$this->laporan->kode_lacak} melewati batas waktu"
                : "Pengingat: batas waktu pengaduan {$this->laporan->kode_lacak} tinggal 2 hari kerja",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pengingat-batas-waktu',
            with: [
                'eskalasi' => $this->eskalasi,
                'kodeLacak' => $this->laporan->kode_lacak,
                'unit' => $this->laporan->unit?->nama,
                'kategori' => $this->laporan->kategori?->nama,
                'pic' => $this->laporan->disposisi()->latest('id')->first()?->pic?->name,
                'batasWaktu' => WaktuLokal::keLokal($this->laporan->batas_waktu)->locale('id')->translatedFormat('l, d F Y'),
                'tautan' => $this->tautan,
            ],
        );
    }
}
