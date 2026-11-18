<?php

namespace Tests\Feature\Http\Controllers;

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

    public function test_beranda_has_no_emergency_contacts_until_they_are_filled_in(): void
    {
        Unit::factory()->create(['kontak_darurat' => null]);

        $response = $this->get('/');

        $response->assertInertia(fn (Assert $page) => $page->where('kontakDarurat', []));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function halamanSementara(): array
    {
        return [
            'faq' => ['/faq', 'Tanya Jawab (FAQ)'],
            'privasi' => ['/privasi', 'Kebijakan Privasi'],
            'wbs' => ['/wbs', 'Pelaporan Pelanggaran (WBS)'],
        ];
    }

    #[DataProvider('halamanSementara')]
    public function test_linked_pages_exist_as_temporary_pages(string $alamat, string $judul): void
    {
        $response = $this->get($alamat);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Sementara')->where('judul', $judul));
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
