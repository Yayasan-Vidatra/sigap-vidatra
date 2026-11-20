<?php

namespace Tests\Feature;

use App\Enums\AksiAudit;
use App\Enums\JenisBerkas;
use App\Enums\JenisLaporan;
use App\Enums\JenisPelapor;
use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Filament\Pages\KodeAksesWbs as HalamanKodeAkses;
use App\Filament\Resources\Pengaduan\Pages\ListPengaduan;
use App\Http\Controllers\WbsController;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\Pengaturan;
use App\Models\RiwayatPengaturan;
use App\Models\TautanBukti;
use App\Models\Unit;
use App\Models\User;
use App\Services\Lampiran\PemeriksaBerkas;
use App\Services\Pengaduan\PenangananPengaduan;
use App\Services\Wbs\AksesWbs;
use App\Services\Wbs\KodeAksesWbs;
use App\Services\Wbs\TokenFormulirWbs;
use Database\Seeders\KontenPublikSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\Concerns\MembuatBerkasUji;
use Tests\Concerns\MembukaKanalWbs;
use Tests\Concerns\MemecahkanAltcha;
use Tests\Concerns\MengunggahLampiran;
use Tests\TestCase;

/**
 * Revisi 20-11 (log deviasi, status Diusulkan): WBS hanya internal dengan kode akses, Lacak dipisah, tidak anonim,
 * PT Vidatra Jaya Makmur sebagai unit khusus WBS, kedudukan "Lain-lain", berkas ZIP, dan tautan Google Drive.
 * Bagian lain diuji di WbsControllerTest (formulir dan identitas wajib) dan LacakWbsTest (Lacak WBS).
 */
class RevisiWbsInternalTest extends TestCase
{
    use MembuatBerkasUji;
    use MembukaKanalWbs;
    use MemecahkanAltcha;
    use MengunggahLampiran;
    use RefreshDatabase;

    public function test_wbs_entry_page_shows_the_access_code_step_with_noindex_and_no_cookie(): void
    {
        $this->aturKodeAkses('Petunjuk: tahun berdirinya Yayasan Vidatra');

        $response = $this->get('/wbs');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wbs/Akses')
            ->where('dibuka', true)
            ->where('petunjuk', 'Petunjuk: tahun berdirinya Yayasan Vidatra'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertNull($response->headers->get('Set-Cookie'));
    }

    public function test_complaint_pages_are_not_marked_noindex_and_robots_txt_does_not_reveal_wbs(): void
    {
        $this->get('/')->assertHeaderMissing('X-Robots-Tag')->assertDontSee('noindex', false);
        $this->assertStringNotContainsStringIgnoringCase('wbs', (string) file_get_contents(public_path('robots.txt')));
    }

    public function test_channel_without_a_configured_code_says_it_is_not_open(): void
    {
        $this->get('/wbs')->assertInertia(fn (Assert $page) => $page
            ->where('dibuka', false)
            ->where('pesanTutup', 'Kanal belum dibuka, hubungi Tim WBS.'));

        $this->postJson('/wbs/akses', ['kode' => 'apa saja', 'altcha' => $this->solusiAltcha()])
            ->assertStatus(423)
            ->assertJsonPath('message', 'Kanal belum dibuka, hubungi Tim WBS.');
    }

    public function test_wrong_code_gets_a_general_message_and_the_right_code_opens_the_assurance(): void
    {
        $kode = $this->aturKodeAkses();

        $this->postJson('/wbs/akses', ['kode' => $kode.'x', 'altcha' => $this->solusiAltcha()])
            ->assertUnprocessable()
            ->assertJsonPath('errors.kode.0', 'Kode akses tidak sesuai.')
            ->assertJsonMissingPath('tokenAkses');

        $benar = $this->postJson('/wbs/akses', ['kode' => '  '.strtoupper($kode).' ', 'altcha' => $this->solusiAltcha()]);

        $benar->assertOk()->assertJsonStructure(['tokenAkses', 'jaminan' => ['berkasJaminan', 'saluran']]);
        $this->assertTrue(KodeAksesWbs::tokenSah($benar->json('tokenAkses')));
        $this->assertSame([], $benar->headers->getCookies());
    }

    public function test_every_code_attempt_needs_a_fresh_captcha(): void
    {
        $kode = $this->aturKodeAkses();
        $solusi = $this->solusiAltcha();

        $this->postJson('/wbs/akses', ['kode' => $kode])->assertJsonValidationErrors('altcha');
        $this->postJson('/wbs/akses', ['kode' => 'salah', 'altcha' => $solusi])->assertJsonValidationErrors('kode');
        $this->postJson('/wbs/akses', ['kode' => $kode, 'altcha' => $solusi])->assertJsonValidationErrors('altcha');
    }

    public function test_access_token_expires(): void
    {
        $this->freezeTime();
        $token = $this->tokenAkses();

        $this->postJson('/wbs/lapor/buka', ['token_akses' => $token])->assertOk();

        $this->travel(KodeAksesWbs::masaTokenMenit() + 1)->minutes();

        $this->postJson('/wbs/lapor/buka', ['token_akses' => $token])
            ->assertStatus(419)
            ->assertJsonPath('message', WbsController::PESAN_TOKEN_AKSES);
        $this->getJson('/wbs/lapor/token', ['X-Token-Akses' => $token])->assertStatus(419);
    }

    public function test_code_is_stored_only_as_a_hash_and_its_change_is_logged_without_the_value(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $kode = 'Rahasia'.random_int(1000, 9999);

        app(KodeAksesWbs::class)->simpan($ketua, $kode, 'Petunjuk baru');

        $tersimpan = Pengaturan::query()->where('kunci', Pengaturan::WBS_KODE_AKSES)->value('nilai');
        $this->assertNotSame($kode, $tersimpan);
        $this->assertStringNotContainsString($kode, $tersimpan);
        $this->assertTrue(Hash::check(mb_strtolower($kode), $tersimpan));
        $this->assertTrue(KodeAksesWbs::cocok($kode));

        $riwayat = RiwayatPengaturan::query()->where('kolom', 'kode_akses')->sole();
        $this->assertSame(RiwayatPengaturan::NILAI_RAHASIA, $riwayat->nilai_baru);
        $this->assertNull($riwayat->nilai_lama);
        $this->assertSame(0, RiwayatPengaturan::query()->where('nilai_baru', 'like', "%{$kode}%")->count());
        $this->assertSame(0, LogAudit::query()->where('objek', 'like', "%{$kode}%")->count());
        $this->assertDatabaseHas('riwayat_pengaturan', ['kolom' => 'nilai', 'nilai_baru' => 'Petunjuk baru']);

        // Menyimpan petunjuk saja tidak mengubah kode dan tidak mencatat perubahan kode.
        app(KodeAksesWbs::class)->simpan($ketua, null, 'Petunjuk baru');
        $this->assertSame($tersimpan, Pengaturan::query()->where('kunci', Pengaturan::WBS_KODE_AKSES)->value('nilai'));
        $this->assertSame(1, RiwayatPengaturan::query()->where('kolom', 'kode_akses')->count());
    }

    public function test_only_super_admin_and_ketua_tim_wbs_manage_the_code_and_the_old_code_is_never_shown(): void
    {
        $this->aturKodeAkses();

        foreach ([Peran::SuperAdmin, Peran::KetuaTimWbs] as $peran) {
            $this->actingAs(User::factory()->peran($peran)->denganMfa()->create());
            $this->get('/panel/pengaturan/kode-akses-wbs')->assertOk();
            Livewire::test(HalamanKodeAkses::class)->assertFormSet(['kode_baru' => null, 'kode_baru_confirmation' => null]);
        }

        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $this->actingAs($ketua);
        Livewire::test(HalamanKodeAkses::class)
            ->fillForm(['kode_baru' => 'kodeBaru123', 'kode_baru_confirmation' => 'kodeBaru123', 'petunjuk' => 'Petunjuk dari Ketua'])
            ->call('simpan')
            ->assertHasNoFormErrors()
            ->assertFormSet(['kode_baru' => null, 'petunjuk' => 'Petunjuk dari Ketua']);
        $this->assertTrue(KodeAksesWbs::cocok('kodebaru123'));

        foreach ([Peran::AdminPengaduan, Peran::AnggotaTimWbs, Peran::DewanPengawas, Peran::KepalaSekolah] as $peran) {
            $user = User::factory()->peran($peran)->denganMfa()->create(['unit_id' => $peran->wajibUnit() ? Unit::factory()->create()->id : null]);
            $this->actingAs($user)->get('/panel/pengaturan/kode-akses-wbs')->assertForbidden();

            try {
                app(KodeAksesWbs::class)->simpan($user, 'dicoba123', null);
                $this->fail('Peran '.$peran->value.' tidak boleh mengubah kode akses.');
            } catch (AuthorizationException) {
                $this->assertTrue(KodeAksesWbs::cocok('kodebaru123'));
            }
        }
    }

    public function test_public_pages_no_longer_mention_wbs(): void
    {
        $this->seed(KontenPublikSeeder::class);

        foreach (['/', '/pengaduan', '/lacak', '/faq', '/privasi'] as $alamat) {
            $response = $this->get($alamat);
            $response->assertOk();

            foreach (['WBS', 'pelanggaran', 'Pelanggaran', '/wbs', 'anonim'] as $kata) {
                $response->assertDontSee($kata, false);
            }
        }
    }

    public function test_pt_vjm_is_offered_only_for_wbs_and_never_for_complaints(): void
    {
        $sekolah = Unit::factory()->create(['nama' => 'SD YPVDP']);
        $ptVjm = Unit::factory()->create(['nama' => 'PT Vidatra Jaya Makmur', 'khusus_wbs' => true]);
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();

        $this->get('/pengaduan')->assertInertia(fn (Assert $page) => $page->where('unit', fn ($unit): bool => collect($unit)->pluck('id')->all() === [$sekolah->id]));
        $this->post('/pengaduan', $this->isianPengaduan(['unit_id' => $ptVjm->id]))->assertSessionHasErrors('unit_id');
        $this->assertFalse(PenangananPengaduan::unitTujuanSah($admin, $ptVjm));
        $this->assertTrue(PenangananPengaduan::unitTujuanSah($admin, $sekolah));

        $this->actingAs($admin);
        Livewire::test(ListPengaduan::class)->assertSee('SD YPVDP')->assertDontSee('PT Vidatra Jaya Makmur');

        $this->postJson('/wbs/lapor/buka', ['token_akses' => $this->tokenAkses()])
            ->assertJsonPath('unit', fn ($unit): bool => collect($unit)->pluck('id')->contains($ptVjm->id));
    }

    public function test_pt_vjm_leader_named_as_reported_party_is_treated_like_a_principal(): void
    {
        Unit::factory()->create(['nama' => 'PT Vidatra Jaya Makmur', 'khusus_wbs' => true, 'nama_pimpinan' => 'Hendra Saputra']);
        $laporan = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::LainLain]);
        $laporan->terlapor()->create(['nama' => 'Pak Hendra Saputra, S.E.', 'jabatan' => 'Direktur']);

        $this->assertSame(KedudukanTerlapor::GuruPegawai, AksesWbs::kedudukanEfektif($laporan->fresh()));
        $this->assertTrue(AksesWbs::pimpinanUnitDisebut($laporan->fresh()));
    }

    public function test_lain_lain_is_routed_to_the_wbs_team_like_tidak_tahu(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $dewas = User::factory()->peran(Peran::DewanPengawas)->denganMfa()->create();
        $laporan = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::LainLain]);
        $laporan->terlapor()->create(['nama' => 'Petugas kantin', 'kedudukan_lain' => 'Rekanan kantin']);

        $this->assertSame(KedudukanTerlapor::TidakTahu->peranPenelaah(), KedudukanTerlapor::LainLain->peranPenelaah());
        $this->assertSame(KedudukanTerlapor::TidakTahu->peranPenerimaHasil(), KedudukanTerlapor::LainLain->peranPenerimaHasil());
        $this->assertSame(KedudukanTerlapor::TidakTahu->tingkat(), KedudukanTerlapor::LainLain->tingkat());
        $this->assertTrue($ketua->can('bukaLaporanWbs', $laporan->fresh()));
        $this->assertFalse($dewas->can('bukaLaporanWbs', $laporan->fresh()));
        $this->assertNotContains(KedudukanTerlapor::TidakTahu, KedudukanTerlapor::pilihanFormulir());
        $this->assertSame('Tidak tahu', KedudukanTerlapor::TidakTahu->getLabel());

        $this->actingAs($ketua)->get("/panel/wbs/{$laporan->id}")->assertOk()->assertSee('Rekanan kantin');
    }

    public function test_name_matching_still_closes_a_lain_lain_report_to_the_named_ketua(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create(['name' => 'Siti Rahmawati']);
        $dewas = User::factory()->peran(Peran::DewanPengawas)->denganMfa()->create();
        $laporan = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::LainLain]);
        $laporan->terlapor()->create(['nama' => 'Bu Siti Rahmawati', 'kedudukan_lain' => 'Koordinator kegiatan']);

        $this->assertFalse($ketua->can('bukaLaporanWbs', $laporan->fresh()));
        $this->assertTrue($dewas->can('bukaLaporanWbs', $laporan->fresh()));
        $this->actingAs($ketua)->get("/panel/wbs/{$laporan->id}")->assertNotFound();
    }

    public function test_zip_archives_up_to_100_mb_are_accepted_for_both_channels(): void
    {
        Storage::fake('lampiran');
        $zip = $this->zipUji();

        foreach ([JenisLaporan::Pengaduan, JenisLaporan::Wbs] as $kanal) {
            $hasil = app(PemeriksaBerkas::class)->periksa($zip, 'bukti.zip', $kanal);
            $this->assertSame(JenisBerkas::Arsip, $hasil['jenis']);
            $this->assertSame('application/zip', $hasil['mime']);
        }

        $this->assertSame(100 * 1024 * 1024, JenisBerkas::Arsip->maksByte());
        $this->unggahBertahap($zip, 'bukti.zip')->assertOk();
        $this->unggahBertahap($zip, 'bukti.zip', 'wbs')->assertOk();
    }

    public function test_complaint_drive_links_are_encrypted_and_need_google_and_consent(): void
    {
        config(['sigap.pengaduan.batas_kirim_per_menit' => 100]);
        $tautan = 'https://drive.google.com/file/d/1AbCdEf/view?usp=sharing';

        $this->post('/pengaduan', $this->isianPengaduan(['tautan' => [$tautan]]))->assertSessionHasErrors('setuju_tautan');
        $this->post('/pengaduan', $this->isianPengaduan(['tautan' => [$tautan], 'setuju_tautan' => false]))->assertSessionHasErrors('setuju_tautan');
        foreach (['https://dropbox.com/s/abc', 'http://drive.google.com/file/d/x', 'https://drive.google.com.evil.test/x', 'https://user:pw@drive.google.com/x', 'javascript:alert(1)'] as $buruk) {
            $this->post('/pengaduan', $this->isianPengaduan(['tautan' => [$buruk], 'setuju_tautan' => true]))->assertSessionHasErrors('tautan.0');
        }
        $this->post('/pengaduan', $this->isianPengaduan(['tautan' => array_fill(0, 6, $tautan), 'setuju_tautan' => true]))->assertSessionHasErrors('tautan');
        $this->assertSame(0, Laporan::query()->count());

        $this->post('/pengaduan', $this->isianPengaduan([
            'tautan' => [$tautan, 'https://docs.google.com/document/d/2XyZ/edit'],
            'setuju_tautan' => true,
        ]))->assertRedirect(route('bukti-lapor'));

        $laporan = Laporan::query()->sole();
        $this->assertSame([$tautan, 'https://docs.google.com/document/d/2XyZ/edit'], $laporan->tautanBukti()->orderBy('id')->pluck('url')->all());
        foreach (DB::table('tautan_bukti')->pluck('url') as $mentah) {
            $this->assertStringNotContainsString('google.com', $mentah);
        }
    }

    public function test_wbs_drive_links_need_consent_and_are_stored_encrypted(): void
    {
        $tautan = 'https://drive.google.com/drive/folders/9ZyX';
        $isian = $this->isianWbs(['tautan' => [$tautan]]);

        $this->postJson('/wbs/lapor', $isian)->assertJsonValidationErrors('setuju_tautan');
        $this->postJson('/wbs/lapor', $this->isianWbs(['tautan' => ['https://onedrive.live.com/x'], 'setuju_tautan' => true]))->assertJsonValidationErrors('tautan.0');

        $this->postJson('/wbs/lapor', $this->isianWbs(['tautan' => [$tautan], 'setuju_tautan' => true]))
            ->assertCreated()
            ->assertJsonPath('jumlahTautan', 1);
        $this->assertSame($tautan, TautanBukti::query()->sole()->url);
        $this->assertStringNotContainsString('drive.google.com', DB::table('tautan_bukti')->value('url'));
    }

    public function test_super_admin_cannot_open_wbs_links_and_authorized_openings_are_logged(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();
        $laporan = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::GuruPegawai]);
        $tautan = TautanBukti::factory()->for($laporan)->create(['url' => 'https://drive.google.com/file/d/rahasia/view']);
        $alamat = $tautan->tautanBukaSementara();

        $this->assertFalse($superAdmin->can('view', $tautan));
        $this->actingAs($superAdmin)->get($alamat)->assertForbidden();
        $this->assertDatabaseMissing('log_audit', ['aksi' => AksiAudit::MembukaTautanBukti->value]);

        $this->actingAs($superAdmin)->get('/panel/log-audit')->assertDontSee('drive.google.com/file/d/rahasia');

        $buka = $this->actingAs($ketua)->get($alamat);
        $buka->assertRedirect('https://drive.google.com/file/d/rahasia/view');
        $buka->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertDatabaseHas('log_audit', ['user_id' => $ketua->id, 'aksi' => AksiAudit::MembukaTautanBukti->value, 'objek' => "tautan_bukti:{$tautan->id}"]);

        $this->actingAs($ketua)->get(route('filament.panel.tautan-bukti.buka', ['tautanBukti' => $tautan->id]))->assertForbidden();

        $halaman = $this->actingAs($ketua)->get("/panel/wbs/{$laporan->id}");
        $halaman->assertOk()->assertSee('Tautan luar, tidak dipindai')->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_pic_sees_complaint_links_but_not_links_sent_through_messages(): void
    {
        $unit = Unit::factory()->create();
        $pic = User::factory()->peran(Peran::PicUnit)->for($unit)->denganMfa()->create();
        $laporan = Laporan::factory()->for($unit)->create();
        $laporan->disposisi()->create(['unit_id' => $unit->id, 'pic_user_id' => $pic->id, 'instruksi' => 'Tindak lanjuti.']);
        $pesan = $laporan->pesan()->create(['pengirim' => 'pelapor', 'isi' => 'Tambahan', 'internal' => false]);
        $dariLaporan = TautanBukti::factory()->for($laporan)->create();
        $dariPesan = TautanBukti::factory()->for($laporan)->create(['pesan_id' => $pesan->id]);

        $this->assertTrue($pic->can('view', $dariLaporan));
        $this->assertFalse($pic->can('view', $dariPesan));
    }

    public function test_complaint_moved_to_wbs_shows_no_wbs_wording_to_the_reporter(): void
    {
        $this->assertStringNotContainsString('WBS', StatusLaporan::DipindahKeWbs->labelUntukPelapor());
        $this->assertStringNotContainsString('WBS', StatusLaporan::DipindahKeWbs->penjelasanUntukPelapor());
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function isianPengaduan(array $ubah = []): array
    {
        return [
            'unit_id' => Unit::factory()->create()->id,
            'kategori_id' => Kategori::factory()->create()->id,
            'jenis_pelapor' => JenisPelapor::OrangTua->value,
            'nama' => 'Rina Kartika',
            'email' => 'rina@example.com',
            'no_hp' => '',
            'judul' => 'Toilet lantai 2 rusak',
            'kronologi' => 'Keran di toilet lantai 2 bocor sejak hari Senin.',
            'setuju_ketentuan' => true,
            'situs_web' => '',
            'altcha' => $this->solusiAltcha(),
            ...$ubah,
        ];
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function isianWbs(array $ubah = []): array
    {
        return [
            'token_akses' => $this->tokenAkses(),
            'token' => TokenFormulirWbs::terbitkan(),
            'nama' => 'Andi Pratama',
            'email' => 'andi@example.com',
            'no_hp' => '0812-3456-7890',
            'kategori_id' => Kategori::factory()->wbs()->create()->id,
            'unit_id' => Unit::factory()->create()->id,
            'terlapor' => [['nama' => 'Bendahara Yayasan', 'jabatan' => 'Bendahara']],
            'kedudukan_terlapor' => KedudukanTerlapor::Pengurus->value,
            'judul' => 'Dana kegiatan dipotong',
            'kronologi' => 'Dana kegiatan siswa dipotong tanpa bukti sejak awal semester ganjil.',
            'pin' => '582904',
            'pin_confirmation' => '582904',
            'altcha' => $this->solusiAltcha(),
            ...$ubah,
        ];
    }
}
