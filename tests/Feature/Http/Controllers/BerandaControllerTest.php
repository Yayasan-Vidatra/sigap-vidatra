<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Kategori;
use App\Models\Pengaturan;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BerandaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_beranda_shows_emergency_contacts_stored_on_active_units(): void
    {
        Unit::factory()->create(['nama' => 'SD YPVDP', 'kontak_darurat' => '0811-1111-1111']);
        Unit::factory()->create(['nama' => 'SMP YPVDP', 'kontak_darurat' => '0822-2222-2222']);
        Unit::factory()->create(['nama' => 'Pelaksana Harian', 'kontak_darurat' => null]);
        Unit::factory()->nonaktif()->create(['nama' => 'Unit Lama', 'kontak_darurat' => '0833-3333-3333']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Beranda')
            ->where('kontakDarurat', [
                ['unit' => 'SD YPVDP', 'kontak' => '0811-1111-1111'],
                ['unit' => 'SMP YPVDP', 'kontak' => '0822-2222-2222'],
            ]));
    }

    public function test_beranda_takes_the_complaint_targets_from_the_category_sla(): void
    {
        Kategori::factory()->create(['nama' => 'Kebersihan', 'sla_tanggapan_hari' => 2, 'sla_hari' => 14]);
        Kategori::factory()->create(['nama' => 'Fasilitas', 'sla_tanggapan_hari' => 3, 'sla_hari' => 10]);
        Kategori::factory()->wbs()->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('targetPengaduan', ['tanggapan' => ['min' => 2, 'maks' => 3], 'selesai' => ['min' => 10, 'maks' => 14]]));
    }

    public function test_changing_a_category_sla_changes_the_beranda_target_without_code(): void
    {
        $kategori = Kategori::factory()->create(['sla_tanggapan_hari' => 2, 'sla_hari' => 14]);

        $kategori->update(['sla_tanggapan_hari' => 1, 'sla_hari' => 7]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('targetPengaduan', ['tanggapan' => ['min' => 1, 'maks' => 1], 'selesai' => ['min' => 7, 'maks' => 7]]));
    }

    public function test_beranda_shows_no_target_before_complaint_categories_exist(): void
    {
        Kategori::factory()->wbs()->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('targetPengaduan', null));
    }

    public function test_the_beranda_page_no_longer_hard_codes_the_sla(): void
    {
        $sumber = file_get_contents(resource_path('js/pages/Beranda.jsx'));

        $this->assertDoesNotMatchRegularExpression('/\d+\s+hari kerja/', $sumber);
    }

    public function test_beranda_has_no_emergency_contacts_until_they_are_filled_in(): void
    {
        Unit::factory()->create(['kontak_darurat' => null]);

        $response = $this->get('/');

        $response->assertInertia(fn (Assert $page) => $page->where('kontakDarurat', []));
    }

    public function test_units_without_an_emergency_contact_get_a_useful_fallback_instead_of_a_missing_number(): void
    {
        Unit::factory()->create(['nama' => 'SD YPVDP', 'kontak_darurat' => '0811-1111-1111']);
        Unit::factory()->create(['nama' => 'SMP YPVDP', 'kontak_darurat' => null]);
        Unit::factory()->create(['nama' => 'SMA YPVDP', 'kontak_darurat' => '']);
        Unit::factory()->nonaktif()->create(['nama' => 'Unit Lama', 'kontak_darurat' => null]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('kontakDarurat', [['unit' => 'SD YPVDP', 'kontak' => '0811-1111-1111']])
            ->where('unitTanpaKontakDarurat', ['SMP YPVDP', 'SMA YPVDP'])
            ->where('kontakDaruratCadangan', 'Segera hubungi wali kelas atau kantor sekolah.'));
    }

    public function test_when_no_unit_has_an_emergency_contact_every_unit_gets_the_fallback(): void
    {
        Unit::factory()->count(2)->create(['kontak_darurat' => null]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('kontakDarurat', [])
            ->has('unitTanpaKontakDarurat', 2)
            ->where('kontakDaruratCadangan', Unit::KONTAK_DARURAT_CADANGAN));
    }

    public function test_public_pages_show_the_fallback_and_never_point_to_a_number_that_is_not_shown(): void
    {
        $halaman = [
            'Beranda.jsx' => 'Hubungi kontak darurat unit di samping',
            'Pengaduan/Formulir.jsx' => 'Hubungi kontak darurat',
            'Wbs/Formulir.jsx' => 'segera minta bantuan',
        ];

        foreach ($halaman as $berkas => $ajakan) {
            $sumber = file_get_contents(resource_path("js/pages/{$berkas}"));

            $this->assertStringContainsString('kontakDaruratCadangan', $sumber, $berkas);
            $this->assertStringContainsString($ajakan, $sumber, $berkas);
            $this->assertStringNotContainsString('belum tersedia', $sumber, $berkas);
        }

        $this->assertStringNotContainsString('{kontak.length > 0 && (', file_get_contents(resource_path('js/pages/Pengaduan/Formulir.jsx')));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function halamanTertaut(): array
    {
        return [
            'faq' => ['/faq', 'Informasi/Faq'],
            'privasi' => ['/privasi', 'Informasi/Privasi'],
        ];
    }

    #[DataProvider('halamanTertaut')]
    public function test_linked_pages_exist(string $alamat, string $komponen): void
    {
        $response = $this->get($alamat);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component($komponen));
    }

    public function test_beranda_takes_the_wbs_review_target_from_config_and_four_questions_from_settings(): void
    {
        config(['sigap.wbs.telaah_hari_kerja' => 7]);
        $faq = collect(range(1, 6))->map(fn (int $i): array => ['pertanyaan' => "Pertanyaan {$i}?", 'jawaban' => "Jawaban {$i}."])->all();
        Pengaturan::factory()->create(['kunci' => Pengaturan::PUBLIK_FAQ, 'nilai' => json_encode($faq)]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('targetWbs', ['telaah' => 7])
            ->has('tanyaJawab', 4)
            ->where('tanyaJawab.0.pertanyaan', 'Pertanyaan 1?'));
    }

    public function test_public_pages_load_fonts_and_images_only_from_this_site(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $vite = file_get_contents(base_path('vite.config.js'));

        $this->assertDoesNotMatchRegularExpression('#url\(\s*[\'"]?https?:#i', $css);
        $this->assertStringNotContainsString('bunny', $vite);
        $this->assertFileExists(resource_path('fonts/plus-jakarta-sans/OFL.txt'));
        $this->assertFileExists(public_path('img/foto-siswa-1800.jpg'));

        foreach (['/', '/wbs', '/pengaduan', '/lacak'] as $alamat) {
            $html = $this->get($alamat)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('#(src|href)="https?://(?!localhost)#i', $html, $alamat);
            $this->assertStringNotContainsString('fonts.googleapis', $html);
        }

        $this->assertStringContainsString("font-src 'self'", $this->get('/wbs')->headers->get('Content-Security-Policy'));
    }

    public function test_wbs_path_sets_no_cookies(): void
    {
        $response = $this->get('/wbs');

        $response->assertOk();
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_other_public_pages_still_use_a_session(): void
    {
        $response = $this->get('/');

        $this->assertNotSame([], $response->headers->getCookies());
    }
}
