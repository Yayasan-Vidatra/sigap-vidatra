<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\Peran;
use App\Models\Kategori;
use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HalamanInformasiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_shows_the_questions_from_settings_and_the_targets_used_by_the_system(): void
    {
        Pengaturan::factory()->create([
            'kunci' => Pengaturan::PUBLIK_FAQ,
            'nilai' => json_encode([['pertanyaan' => 'Apakah identitas saya aman?', 'jawaban' => 'Ya, dienkripsi.']]),
        ]);
        Kategori::factory()->create(['nama' => 'Kebersihan', 'sla_tanggapan_hari' => 2, 'sla_hari' => 14]);
        Kategori::factory()->wbs()->create(['nama' => 'Suap atau gratifikasi']);
        config(['sigap.wbs.telaah_hari_kerja' => 5, 'sigap.wbs.investigasi_hari_kerja' => 30, 'sigap.wbs.perpanjangan_hari_kerja' => 30]);

        $this->get('/faq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Informasi/Faq')
                ->where('pertanyaan', [['pertanyaan' => 'Apakah identitas saya aman?', 'jawaban' => 'Ya, dienkripsi.']])
                ->where('targetPengaduan', [['kategori' => 'Kebersihan', 'tanggapan' => 2, 'selesai' => 14]])
                ->where('targetWbs', ['telaah' => 5, 'investigasi' => 30, 'perpanjangan' => 30]));
    }

    public function test_changing_the_text_in_settings_changes_the_page_without_code(): void
    {
        $privasi = Pengaturan::factory()->create([
            'kunci' => Pengaturan::PUBLIK_PRIVASI,
            'nilai' => json_encode([['judul' => 'Lama penyimpanan', 'isi' => '5 tahun.']]),
        ]);

        $this->get('/privasi')->assertInertia(fn (Assert $page) => $page
            ->component('Informasi/Privasi')
            ->where('bagian', [['judul' => 'Lama penyimpanan', 'isi' => '5 tahun.']]));

        $privasi->update(['nilai' => json_encode([['judul' => 'Lama penyimpanan', 'isi' => 'Lima tahun setelah ditutup.']])]);

        $this->get('/privasi')->assertInertia(fn (Assert $page) => $page
            ->where('bagian.0.isi', 'Lima tahun setelah ditutup.'));
    }

    public function test_pages_render_an_empty_state_before_text_is_filled(): void
    {
        $this->get('/faq')->assertOk()->assertInertia(fn (Assert $page) => $page->where('pertanyaan', []));
        $this->get('/privasi')->assertOk()->assertInertia(fn (Assert $page) => $page->where('bagian', []));
    }

    public function test_public_pages_do_not_reveal_the_wbs_team_or_result_recipients(): void
    {
        $nama = [];

        foreach ([Peran::KetuaTimWbs, Peran::AnggotaTimWbs, Peran::DewanPengawas, Peran::KetuaPembina, Peran::KetuaUmum] as $i => $peran) {
            $user = User::factory()->peran($peran)->create(['name' => "Petugas Rahasia {$i}", 'email' => "rahasia{$i}@contoh.test"]);
            $nama[] = $user->name;
            $nama[] = $user->email;
        }

        foreach (['/', '/faq', '/privasi', '/wbs'] as $alamat) {
            $response = $this->get($alamat)->assertOk();

            foreach ($nama as $teks) {
                $response->assertDontSee($teks);
            }
        }
    }
}
