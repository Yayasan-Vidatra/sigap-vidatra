<?php

namespace App\Mail;

use App\Enums\StatusLaporan;
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
            subject: "Status pengaduan {$this->laporan->kode_lacak}: {$this->riwayat->status_baru->getLabel()}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.status-pengaduan-berubah',
            with: [
                'kodeLacak' => $this->laporan->kode_lacak,
                'status' => $this->riwayat->status_baru->getLabel(),
                'waktu' => WaktuLokal::tampilkan($this->riwayat->waktu, 'd F Y, H.i'),
                'catatan' => $this->riwayat->tampil_ke_pelapor ? $this->riwayat->catatan : null,
                'penjelasan' => self::penjelasan($this->riwayat->status_baru),
                'tautanLacak' => route('lacak'),
            ],
        );
    }

    private static function penjelasan(StatusLaporan $status): string
    {
        return match ($status) {
            StatusLaporan::Terverifikasi => 'Pengaduan Anda sudah diperiksa dan akan diteruskan ke unit terkait.',
            StatusLaporan::Diproses => 'Pengaduan Anda sedang ditangani unit terkait.',
            StatusLaporan::MenungguKonfirmasi => 'Unit sudah menindaklanjuti pengaduan Anda. Mohon buka halaman Lacak Laporan untuk '
                .'mengonfirmasi atau menolak hasilnya dalam '.config('sigap.pengaduan.hari_konfirmasi').' hari. Tanpa tanggapan, '
                .'pengaduan dianggap selesai.',
            StatusLaporan::Selesai => 'Penanganan pengaduan Anda sudah selesai. Terima kasih atas laporan Anda.',
            StatusLaporan::Ditolak => 'Pengaduan Anda tidak dapat ditindaklanjuti.',
            default => 'Status pengaduan Anda diperbarui.',
        };
    }
}
