<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\JenisPelapor;
use App\Http\Controllers\BuktiLaporController;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MembacaPdf;
use Tests\Concerns\MemecahkanAltcha;
use Tests\TestCase;

/**
 * PDF bukti lapor (H-05, US-07): dari halaman Bukti Lapor, kode dari sesi, berlaku 10 menit, tidak disimpan.
 */
class BuktiLaporControllerTest extends TestCase
{
    use MembacaPdf;
    use MemecahkanAltcha;
    use RefreshDatabase;

    public function test_receipt_pdf_downloads_with_the_tracking_code_channel_time_and_tracking_address(): void
    {
        $this->travelTo('2026-11-17 03:15:00');
        $this->kirimPengaduan();
        $kode = Laporan::query()->sole()->kode_lacak;

        $response = $this->get('/selesai/pdf');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertDownload('bukti-lapor-sigap.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $teks = $this->teksPdf($response->getContent());
        $this->assertStringContainsString($kode, $teks);
        $this->assertStringContainsString('Pengaduan', $teks);
        $this->assertStringContainsString('17 November 2026, 10.15 WIB', $teks);
        $this->assertStringContainsString(route('lacak'), $teks);
        $this->assertStringContainsString('Simpan dokumen ini', $teks);
    }

    public function test_receipt_pdf_contains_no_identity_or_report_content(): void
    {
        $this->kirimPengaduan();

        $teks = $this->teksPdf($this->get('/selesai/pdf')->getContent());

        // Kontrol positif: teks PDF memang terbaca, sehingga pemeriksaan di bawah bermakna.
        $this->assertStringContainsString(Laporan::query()->sole()->kode_lacak, $teks);
        foreach (['Rina Kartika', 'rina.kartika@example.com', '0812-3456-7890', 'Toilet lantai 2 rusak', 'bocor sejak hari Senin', 'Wali kelas', 'Gedung B'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $teks);
        }
    }

    public function test_receipt_pdf_is_refused_after_ten_minutes(): void
    {
        $this->kirimPengaduan();

        $this->travel(9)->minutes();
        $this->get('/selesai/pdf')->assertOk();

        $this->travel(2)->minutes();
        $this->get('/selesai/pdf')->assertForbidden();
    }

    public function test_receipt_pdf_is_refused_without_a_submission_in_this_session(): void
    {
        Laporan::factory()->create();

        $this->get('/selesai/pdf')->assertForbidden();
    }

    public function test_download_url_never_contains_the_tracking_code(): void
    {
        $this->kirimPengaduan();
        $kode = Laporan::query()->sole()->kode_lacak;

        $this->assertSame(url('/selesai/pdf'), route('bukti-lapor.pdf'));
        $this->assertStringNotContainsString($kode, route('bukti-lapor.pdf'));
    }

    public function test_receipt_pdf_is_not_stored_on_the_server(): void
    {
        Storage::fake('local');
        Storage::fake('lampiran');
        $this->kirimPengaduan();

        $this->get('/selesai/pdf')->assertOk();

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('lampiran')->allFiles());
    }

    public function test_wbs_receipt_mentions_the_pin_without_showing_it(): void
    {
        $this->withSession([BuktiLaporController::KUNCI_SESI_PDF => [
            'kode_lacak' => 'WBS-7KQ2-M9PA',
            'jenis' => 'wbs',
            'dikirim_pada' => now()->getTimestamp(),
            'kedaluwarsa' => now()->addMinutes(10)->getTimestamp(),
        ]]);

        $teks = $this->teksPdf($this->get('/selesai/pdf')->getContent());

        $this->assertStringContainsString('WBS-7KQ2-M9PA', $teks);
        $this->assertStringContainsString('Pelaporan Pelanggaran (WBS)', $teks);
        $this->assertStringContainsString('PIN tidak tercantum', $teks);
    }

    public function test_pdf_loads_nothing_from_outside(): void
    {
        $html = view('pdf.bukti-lapor', [
            'kodeLacak' => 'PGD-2611-7KQ2M9',
            'wbs' => false,
            'dikirimPada' => 'Selasa, 17 November 2026, 10.15 WIB',
            'tautanLacak' => route('lacak'),
        ])->render();

        $this->assertFalse(config('dompdf.options.enable_remote'));
        $this->assertFalse(config('dompdf.options.enable_javascript'));
        $this->assertFalse(config('dompdf.options.enable_php'));
        $this->assertSame('DejaVu Sans', config('dompdf.options.default_font'));
        foreach (['<link', '<img', '<script', '@import', 'url(', 'src='] as $sumberLuar) {
            $this->assertStringNotContainsString($sumberLuar, $html);
        }
    }

    private function kirimPengaduan(): void
    {
        $this->post('/pengaduan', [
            'unit_id' => Unit::factory()->create()->id,
            'kategori_id' => Kategori::factory()->create()->id,
            'jenis_pelapor' => JenisPelapor::OrangTua->value,
            'nama' => 'Rina Kartika',
            'email' => 'rina.kartika@example.com',
            'no_hp' => '0812-3456-7890',
            'judul' => 'Toilet lantai 2 rusak',
            'kronologi' => 'Keran di toilet lantai 2 bocor sejak hari Senin.',
            'lokasi' => 'Gedung B',
            'jabatan_pihak_dikeluhkan' => 'Wali kelas',
            'setuju_ketentuan' => true,
            'altcha' => $this->solusiAltcha(),
        ])->assertRedirect(route('bukti-lapor'));
    }
}
