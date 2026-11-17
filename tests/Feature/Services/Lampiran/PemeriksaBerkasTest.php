<?php

namespace Tests\Feature\Services\Lampiran;

use App\Enums\JenisBerkas;
use App\Enums\JenisLaporan;
use App\Services\Lampiran\BerkasDitolak;
use App\Services\Lampiran\PemeriksaBerkas;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MembuatBerkasUji;
use Tests\TestCase;

class PemeriksaBerkasTest extends TestCase
{
    use MembuatBerkasUji;

    private const MB = 1024 * 1024;

    /**
     * @return array<string, array{string, string, JenisBerkas}>
     */
    public static function berkasDiterima(): array
    {
        return [
            'pdf' => ['pdfUji', 'application/pdf', JenisBerkas::Dokumen],
            'docx' => ['docxUji', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', JenisBerkas::Dokumen],
            'xlsx' => ['xlsxUji', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', JenisBerkas::Dokumen],
            'txt' => ['txtUji', 'text/plain', JenisBerkas::Dokumen],
            'jpg' => ['jpgUji', 'image/jpeg', JenisBerkas::Foto],
            'png' => ['pngUji', 'image/png', JenisBerkas::Foto],
            'heic' => ['heicUji', 'image/heic', JenisBerkas::Foto],
            'mp4' => ['mp4Uji', 'video/mp4', JenisBerkas::Video],
            'mov' => ['movUji', 'video/quicktime', JenisBerkas::Video],
        ];
    }

    #[DataProvider('berkasDiterima')]
    public function test_accepts_allowed_formats_by_content(string $pembuat, string $mime, JenisBerkas $jenis): void
    {
        $hasil = (new PemeriksaBerkas)->periksa($this->{$pembuat}(), 'lampiran', JenisLaporan::Pengaduan);

        $this->assertSame($mime, $hasil['mime']);
        $this->assertSame($jenis, $hasil['jenis']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function berkasMenyamar(): array
    {
        return [
            'skrip PHP bernama .pdf' => ['skripMenyamarUji'],
            'program Windows bernama .jpg' => ['programMenyamarUji'],
        ];
    }

    #[DataProvider('berkasMenyamar')]
    public function test_rejects_files_whose_content_does_not_match_an_allowed_format(string $pembuat): void
    {
        $this->expectException(BerkasDitolak::class);
        $this->expectExceptionMessage('format berkas tidak diterima');

        (new PemeriksaBerkas)->periksa($this->{$pembuat}(), 'tagihan.pdf', JenisLaporan::Pengaduan);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dokumenBermakro(): array
    {
        return [
            'docx' => ['docxUji'],
            'xlsx' => ['xlsxUji'],
        ];
    }

    #[DataProvider('dokumenBermakro')]
    public function test_rejects_office_documents_with_macros(string $pembuat): void
    {
        $this->expectException(BerkasDitolak::class);
        $this->expectExceptionMessage('berisi makro');

        (new PemeriksaBerkas)->periksa($this->{$pembuat}(makro: true), 'surat.docx', JenisLaporan::Pengaduan);
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function batasUkuran(): array
    {
        return [
            'dokumen 10 MB' => ['pdfUji', 10, 'dokumen paling besar 10 MB'],
            'foto 10 MB' => ['jpgUji', 10, 'foto paling besar 10 MB'],
            'video 100 MB' => ['mp4Uji', 100, 'video paling besar 100 MB'],
        ];
    }

    #[DataProvider('batasUkuran')]
    public function test_accepts_a_file_at_its_size_limit(string $pembuat, int $maksMb, string $pesan): void
    {
        $hasil = (new PemeriksaBerkas)->periksa($this->{$pembuat}(ukuran: $maksMb * self::MB), 'lampiran', JenisLaporan::Pengaduan);

        $this->assertSame($maksMb * self::MB, $hasil['ukuran']);
    }

    #[DataProvider('batasUkuran')]
    public function test_rejects_a_file_just_over_its_size_limit(string $pembuat, int $maksMb, string $pesan): void
    {
        $this->expectException(BerkasDitolak::class);
        $this->expectExceptionMessage($pesan);

        (new PemeriksaBerkas)->periksa($this->{$pembuat}(ukuran: $maksMb * self::MB + 1), 'lampiran', JenisLaporan::Pengaduan);
    }

    public function test_audio_is_rejected_for_pengaduan_but_accepted_for_wbs(): void
    {
        $pemeriksa = new PemeriksaBerkas;

        $hasil = $pemeriksa->periksa($this->mp3Uji(), 'rekaman.mp3', JenisLaporan::Wbs);

        $this->assertSame(JenisBerkas::Audio, $hasil['jenis']);
        $this->expectException(BerkasDitolak::class);
        $this->expectExceptionMessage('audio tidak diterima di kanal ini');
        $pemeriksa->periksa($this->mp3Uji(), 'rekaman.mp3', JenisLaporan::Pengaduan);
    }

    public function test_rejects_an_empty_file(): void
    {
        $this->expectException(BerkasDitolak::class);
        $this->expectExceptionMessage('berkas kosong');

        (new PemeriksaBerkas)->periksa($this->berkasUji('kosong.pdf'), 'kosong.pdf', JenisLaporan::Pengaduan);
    }
}
