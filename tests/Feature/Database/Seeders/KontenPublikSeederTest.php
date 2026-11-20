<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Pengaturan;
use Database\Seeders\KontenPublikSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KontenPublikSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_the_draft_faq_and_privacy_policy(): void
    {
        $this->seed(KontenPublikSeeder::class);

        $this->assertSame(KontenPublikSeeder::FAQ, Pengaturan::daftar(Pengaturan::PUBLIK_FAQ));
        $this->assertSame(KontenPublikSeeder::PRIVASI, Pengaturan::daftar(Pengaturan::PUBLIK_PRIVASI));
    }

    public function test_the_draft_states_the_system_rules(): void
    {
        $privasi = collect(KontenPublikSeeder::PRIVASI)->pluck('isi')->implode("\n");
        $faq = collect(KontenPublikSeeder::FAQ)->pluck('jawaban')->implode("\n");

        $this->assertStringContainsString('alamat IP tidak disimpan bersama laporan', $privasi);
        $this->assertStringContainsString('tidak memakai cookie iklan atau analitik pihak ketiga', $privasi);
        $this->assertStringContainsString('terenkripsi', $privasi);
        $this->assertStringContainsString('5 tahun setelah laporan ditutup', $privasi);
        $this->assertStringContainsString('UU 27/2022', $privasi);
        $this->assertStringContainsString('tanpa membuka identitas pelapor', $privasi);
        $this->assertStringContainsString('ZIP', $faq);
        $this->assertStringContainsString('tidak dapat dipindai antivirus', $faq);
    }

    /**
     * Revisi 20-11: kanal WBS hanya internal, sehingga FAQ dan Kebijakan Privasi publik tidak menyebutnya, dan mode
     * anonim tidak ada lagi.
     */
    public function test_the_draft_does_not_mention_the_internal_wbs_channel(): void
    {
        $teks = json_encode([KontenPublikSeeder::FAQ, KontenPublikSeeder::PRIVASI], JSON_UNESCAPED_UNICODE);

        foreach (['WBS', 'pelanggaran', 'Pelanggaran', 'anonim', '/wbs', 'PIN'] as $kata) {
            $this->assertStringNotContainsString($kata, $teks);
        }
    }

    public function test_the_draft_seeds_the_access_code_hint_but_never_a_code(): void
    {
        $this->seed(KontenPublikSeeder::class);

        $this->assertSame('Petunjuk: tahun berdirinya Yayasan Vidatra', Pengaturan::nilai(Pengaturan::WBS_PETUNJUK_KODE_AKSES));
        $this->assertNull(Pengaturan::nilai(Pengaturan::WBS_KODE_AKSES));
    }

    public function test_the_draft_names_no_official_and_no_contact(): void
    {
        $teks = json_encode([KontenPublikSeeder::FAQ, KontenPublikSeeder::PRIVASI], JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('@', $teks);
        $this->assertDoesNotMatchRegularExpression('/\d{6,}/', $teks);
    }

    public function test_it_does_not_overwrite_text_already_edited_in_settings(): void
    {
        Pengaturan::factory()->create(['kunci' => Pengaturan::PUBLIK_FAQ, 'nilai' => json_encode([['pertanyaan' => 'Sudah diubah', 'jawaban' => 'Ya']])]);

        $this->seed(KontenPublikSeeder::class);

        $this->assertSame([['pertanyaan' => 'Sudah diubah', 'jawaban' => 'Ya']], Pengaturan::daftar(Pengaturan::PUBLIK_FAQ));
    }
}
