<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pemberitahuan WBS untuk petugas menurut matriks penerima (F-12, F-19). Sengaja tanpa kode lacak, isi laporan,
 * jenis pelanggaran, nama terlapor, maupun identitas pelapor: hanya bahwa ada sesuatu yang perlu dibuka, dan
 * tautan ke panel yang memeriksa ulang hak akses penerimanya.
 */
class PemberitahuanWbs extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const LAPORAN_BARU = 'laporan_baru';

    public const DIALIHKAN = 'dialihkan';

    public const DITUGASKAN = 'ditugaskan';

    public const PESAN_BARU = 'pesan_baru';

    public const HASIL = 'hasil';

    public const PERPANJANGAN = 'perpanjangan';

    public function __construct(public string $jenis, public string $tautan)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->jenis) {
            self::LAPORAN_BARU => 'Ada laporan WBS baru untuk ditelaah',
            self::DIALIHKAN => 'Laporan WBS dialihkan kepada Anda',
            self::DITUGASKAN => 'Anda ditunjuk sebagai investigator laporan WBS',
            self::HASIL => 'Hasil laporan WBS untuk Anda',
            self::PERPANJANGAN => 'Permintaan persetujuan perpanjangan investigasi WBS',
            default => 'Ada pesan baru dari pelapor WBS',
        });
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pemberitahuan-wbs',
            with: [
                'kalimat' => match ($this->jenis) {
                    self::LAPORAN_BARU => 'Ada laporan pelanggaran baru yang perlu Anda telaah menurut matriks penerima.',
                    self::DIALIHKAN => 'Setelah kedudukan terlapor diperiksa, sebuah laporan pelanggaran kini menjadi bagian Anda menurut matriks penerima.',
                    self::DITUGASKAN => 'Anda ditunjuk sebagai investigator sebuah laporan pelanggaran.',
                    self::HASIL => 'Tim WBS menyampaikan kesimpulan dan rekomendasi sebuah laporan pelanggaran yang menjadi bagian Anda menurut matriks penerima.',
                    self::PERPANJANGAN => 'Tim WBS meminta persetujuan Anda untuk memperpanjang investigasi sebuah laporan pelanggaran.',
                    default => 'Pelapor mengirim pesan baru pada laporan pelanggaran yang Anda tangani.',
                },
                'tautan' => $this->tautan,
            ],
        );
    }
}
