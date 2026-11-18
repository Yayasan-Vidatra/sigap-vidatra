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
 * Surel konfirmasi pengaduan berisi kode lacak (UC-01 langkah 6, US-07), dikirim lewat queue saat pengaduan
 * diterima (listener KirimSurelStatusKePelapor). SMTP Google Workspace dipasang di Hari 14.
 */
class KonfirmasiPengaduan extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Laporan $laporan)
    {
        $this->afterCommit();
    }

    /**
     * Alamat surel pelapor: surel pelapor, atau kontak orang tua/wali bila berupa surel (pelapor siswa).
     * null bila pelapor hanya memberi nomor HP; F-12 hanya mengirim surel bila pelapor memberi surel.
     */
    public static function alamatTujuan(Laporan $laporan): ?string
    {
        return $laporan->identitasPelapor?->alamatSurel();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pengaduan Anda sudah diterima ({$this->laporan->kode_lacak})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.konfirmasi-pengaduan',
            with: [
                'kodeLacak' => $this->laporan->kode_lacak,
                'nama' => $this->laporan->identitasPelapor?->nama,
                'judul' => $this->laporan->judul,
                'unit' => $this->laporan->unit?->nama,
                'diterimaPada' => $this->laporan->dibuat_pada ? WaktuLokal::tampilkan($this->laporan->dibuat_pada, 'd F Y, H.i') : null,
                'tautanLacak' => route('lacak'),
            ],
        );
    }
}
