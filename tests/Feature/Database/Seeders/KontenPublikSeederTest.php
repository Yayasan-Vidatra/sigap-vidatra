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

        $this->assertStringContainsString('tidak mencatat alamat IP', $privasi);
        $this->assertStringContainsString('tidak memasang cookie', $privasi);
        $this->assertStringContainsString('terenkripsi', $privasi);
        $this->assertStringContainsString('5 tahun setelah laporan ditutup', $privasi);
        $this->assertStringContainsString('UU 27/2022', $privasi);
        $this->assertStringContainsString('tanpa membuka identitas pelapor', $privasi);
        $this->assertStringContainsString('tidak mencatat alamat IP', $faq);
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
