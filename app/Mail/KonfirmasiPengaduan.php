<?php

namespace App\Mail;

use App\Models\Laporan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Surel konfirmasi pengaduan berisi kode lacak (UC-01 langkah 6, US-07). Disiapkan di Hari 5;
 * pengirimannya lewat queue dan SMTP Google Workspace dipasang di Hari 7 (US-11, F-12).
 */
class KonfirmasiPengaduan extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Laporan $laporan) {}

    /**
     * Alamat surel pelapor: surel pelapor, atau kontak orang tua/wali bila berupa surel (pelapor siswa).
     * null bila pelapor hanya memberi nomor HP; F-12 hanya mengirim surel bila pelapor memberi surel.
     */
    public static function alamatTujuan(Laporan $laporan): ?string
    {
        $identitas = $laporan->identitasPelapor;

        foreach ([$identitas?->email, $identitas?->kontak_orang_tua] as $calon) {
            if (is_string($calon) && filter_var($calon, FILTER_VALIDATE_EMAIL)) {
                return $calon;
            }
        }

        return null;
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
                'diterimaPada' => $this->laporan->dibuat_pada?->translatedFormat('d M Y H:i'),
                'tautanLacak' => route('lacak'),
            ],
        );
    }
}
