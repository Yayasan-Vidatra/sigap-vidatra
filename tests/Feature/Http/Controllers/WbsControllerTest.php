<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Pengaturan;
use App\Models\Unit;
use App\Services\Wbs\KodeAksesWbs;
use App\Services\Wbs\TokenFormulirWbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use ReflectionProperty;
use Tests\Concerns\MembacaPdf;
use Tests\Concerns\MembuatBerkasUji;
use Tests\Concerns\MembukaKanalWbs;
use Tests\Concerns\MemecahkanAltcha;
use Tests\Concerns\MengunggahLampiran;
use Tests\TestCase;

/**
 * Kanal WBS: kode akses, H-03 Jaminan Kerahasiaan, H-04 Formulir WBS, dan H-05 Bukti Lapor WBS (US-05, US-06, US-07;
 * revisi 20-11: kanal internal dengan kode akses, tidak anonim).
 */
class WbsControllerTest extends TestCase
{
    use MembacaPdf;
    use MembuatBerkasUji;
    use MembukaKanalWbs;
    use MemecahkanAltcha;
    use MengunggahLampiran;
    use RefreshDatabase;

    public function test_assurance_shows_default_text_after_the_access_code_until_a_document_is_configured(): void
    {
        $kode = $this->aturKodeAkses();

        $response = $this->periksaKode($kode);

        $response->assertOk()
            ->assertJsonPath('jaminan.berkasJaminan', null)
            ->assertJsonPath('jaminan.saluran', ['surel' => null, 'telepon' => null, 'surat' => null]);
        $this->assertTrue(KodeAksesWbs::tokenSah($response->json('tokenAkses')));
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_assurance_reads_the_document_and_alternative_channels_from_settings(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pengaturan/jaminan.pdf', file_get_contents($this->pdfUji()));
        Pengaturan::factory()->create(['kunci' => Pengaturan::WBS_BERKAS_JAMINAN, 'nilai' => 'pengaturan/jaminan.pdf']);
        Pengaturan::factory()->create(['kunci' => Pengaturan::WBS_SALURAN_SUREL, 'nilai' => 'wbs@vidatra.sch.id']);
        Pengaturan::factory()->create(['kunci' => Pengaturan::WBS_SALURAN_TELEPON, 'nilai' => '0811-0000-0000']);
        $kode = $this->aturKodeAkses();

        $this->periksaKode($kode)
            ->assertJsonPath('jaminan.berkasJaminan', route('wbs.jaminan.berkas'))
            ->assertJsonPath('jaminan.saluran.surel', 'wbs@vidatra.sch.id')
            ->assertJsonPath('jaminan.saluran.telepon', '0811-0000-0000')
            ->assertJsonPath('jaminan.saluran.surat', null);

        $berkas = $this->get('/wbs/jaminan-kerahasiaan');
        $berkas->assertOk();
        $berkas->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $berkas->headers->get('Content-Disposition'));
        $berkas->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_missing_assurance_document_is_not_served(): void
    {
        Storage::fake('local');
        Pengaturan::factory()->create(['kunci' => Pengaturan::WBS_BERKAS_JAMINAN, 'nilai' => 'pengaturan/hilang.pdf']);

        $this->periksaKode($this->aturKodeAkses())->assertJsonPath('jaminan.berkasJaminan', null);
        $this->get('/wbs/jaminan-kerahasiaan')->assertNotFound();
    }

    public function test_old_form_address_returns_to_the_access_code(): void
    {
        $this->get('/wbs/lapor')->assertRedirect(route('wbs'));
        $this->get('/wbs/lapor?mengerti=1')->assertRedirect(route('wbs'));
    }

    public function test_form_data_needs_a_valid_access_token(): void
    {
        $this->postJson('/wbs/lapor/buka', [])->assertStatus(419);
        $this->postJson('/wbs/lapor/buka', ['token_akses' => 'palsu.palsu'])->assertStatus(419);
        $this->postJson('/wbs/lapor/buka', ['token_akses' => TokenFormulirWbs::terbitkan()])->assertStatus(419);
    }

    public function test_form_offers_wbs_categories_active_units_and_positions_without_tidak_tahu(): void
    {
        $unit = Unit::factory()->create(['nama' => 'SMA YPVDP', 'kontak_darurat' => '0812-1111-2222']);
        $ptVjm = Unit::factory()->create(['nama' => 'PT Vidatra Jaya Makmur', 'khusus_wbs' => true]);
        Unit::factory()->nonaktif()->create();
        $kategori = Kategori::factory()->wbs()->create(['nama' => 'Suap atau gratifikasi']);
        Kategori::factory()->create();

        $response = $this->postJson('/wbs/lapor/buka', ['token_akses' => $this->tokenAkses()]);

        $response->assertOk()
            ->assertJsonPath('unit', [['id' => $unit->id, 'nama' => 'SMA YPVDP'], ['id' => $ptVjm->id, 'nama' => 'PT Vidatra Jaya Makmur']])
            ->assertJsonPath('kategori', [['id' => $kategori->id, 'nama' => 'Suap atau gratifikasi']])
            ->assertJsonCount(count(KedudukanTerlapor::cases()) - 1, 'kedudukan')
            ->assertJsonPath('kedudukan.5', ['value' => 'lain_lain', 'label' => 'Lain-lain'])
            ->assertJsonPath('kontakDarurat', [['unit' => 'SMA YPVDP', 'kontak' => '0812-1111-2222']])
            ->assertJsonPath('jumlahUnitSekolah', 1)
            ->assertJsonPath('kontakDaruratCadangan', 'Segera hubungi wali kelas atau kantor sekolah.')
            ->assertJsonPath('targetTelaah', (int) config('sigap.wbs.telaah_hari_kerja'))
            ->assertJsonPath('maksTautan', 5);
        $this->assertNotContains('tidak_tahu', array_column($response->json('kedudukan'), 'value'));
        $this->assertNotNull(TokenFormulirWbs::nonce($response->json('token')));
        $this->assertTrue(KodeAksesWbs::tokenSah($response->json('tokenAkses')));
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_report_is_stored_encrypted_with_the_required_identity(): void
    {
        Mail::fake();
        $isian = $this->isianSah();

        $response = $this->kirim($isian);

        $response->assertCreated();
        $laporan = Laporan::query()->sole();
        $this->assertMatchesRegularExpression('/^WBS-[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/', $laporan->kode_lacak);
        $response->assertJsonPath('kodeLacak', $laporan->kode_lacak);
        $this->assertSame(JenisLaporan::Wbs, $laporan->jenis);
        $this->assertSame(SumberLaporan::Laman, $laporan->sumber);
        $this->assertSame(StatusLaporan::Diterima, $laporan->status);
        $this->assertSame(KedudukanTerlapor::Pengurus, $laporan->kedudukan_terlapor);
        $this->assertSame('Andi Pratama', $laporan->identitasPelapor->nama);
        $this->assertSame(StatusLaporan::Diterima, $laporan->riwayatStatus()->sole()->status_baru);

        $mentah = DB::table('laporan')->where('id', $laporan->id)->first();
        foreach (['judul', 'kronologi', 'lokasi', 'saksi'] as $kolom) {
            $this->assertNotSame($isian[$kolom], $mentah->{$kolom}, "Kolom {$kolom} tersimpan sebagai teks biasa");
            $this->assertSame($isian[$kolom], Crypt::decryptString($mentah->{$kolom}));
        }

        $terlapor = DB::table('terlapor_wbs')->where('laporan_id', $laporan->id)->orderBy('id')->get();
        $this->assertCount(2, $terlapor);
        $this->assertSame('Bendahara Yayasan', Crypt::decryptString($terlapor[0]->nama));
        $this->assertSame('Bendahara', Crypt::decryptString($terlapor[0]->jabatan));
        $this->assertSame('Orang tidak dikenal', Crypt::decryptString($terlapor[1]->nama));
        $this->assertNull($terlapor[1]->jabatan);
        $this->assertNull($terlapor[0]->kedudukan_lain);

        Mail::assertNothingOutgoing();
    }

    public function test_pin_is_stored_only_as_a_hash(): void
    {
        $this->kirim($this->isianSah())->assertCreated();

        $pinHash = DB::table('laporan')->value('pin_hash');
        $this->assertNotSame('582904', $pinHash);
        $this->assertStringNotContainsString('582904', $pinHash);
        $this->assertTrue(Hash::check('582904', $pinHash));
    }

    public function test_identity_is_stored_encrypted_with_optional_social_media_and_without_parent_contact(): void
    {
        $this->kirim([...$this->isianSah(), 'media_sosial' => 'IG @andi.pratama'])->assertCreated();

        $identitas = DB::table('identitas_pelapor')->sole();
        $this->assertSame('Andi Pratama', Crypt::decryptString($identitas->nama));
        $this->assertSame('andi@example.com', Crypt::decryptString($identitas->email));
        $this->assertSame('0812-3456-7890', Crypt::decryptString($identitas->no_hp));
        $this->assertSame('IG @andi.pratama', Crypt::decryptString($identitas->media_sosial));
        $this->assertStringNotContainsString('andi.pratama', $identitas->media_sosial);
        $this->assertNull($identitas->kontak_orang_tua);
        $this->assertStringNotContainsString('Andi', $identitas->nama);
    }

    public function test_name_email_and_phone_are_required_and_anonymous_mode_is_gone(): void
    {
        $isian = $this->isianSah();

        $this->kirim([...$isian, 'nama' => '', 'email' => '', 'no_hp' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nama', 'email', 'no_hp']);
        $this->kirim([...$isian, 'mode' => 'anonim', 'nama' => ''])->assertJsonValidationErrors('nama');
        $this->kirim([...$isian, 'email' => 'bukan-surel'])->assertJsonValidationErrors('email');
        $this->kirim([...$isian, 'media_sosial' => str_repeat('a', 151)])->assertJsonValidationErrors('media_sosial');

        $pesan = $this->kirim([...$isian, 'nama' => ''])->json('errors.nama.0');
        $this->assertStringContainsString('dirahasiakan dan hanya dibuka oleh penelaah yang berwenang', $pesan);
        $this->assertSame(0, Laporan::query()->count());
    }

    public function test_lain_lain_needs_a_short_position_and_tidak_tahu_can_no_longer_be_chosen(): void
    {
        $isian = [...$this->isianSah(), 'kedudukan_terlapor' => KedudukanTerlapor::LainLain->value];

        $this->kirim($isian)->assertJsonValidationErrors('kedudukan_lain');
        $this->kirim([...$isian, 'kedudukan_lain' => str_repeat('a', 101)])->assertJsonValidationErrors('kedudukan_lain');
        $this->kirim([...$this->isianSah(), 'kedudukan_terlapor' => KedudukanTerlapor::TidakTahu->value])->assertJsonValidationErrors('kedudukan_terlapor');
        $this->assertSame(0, Laporan::query()->count());

        $this->kirim([...$isian, 'kedudukan_lain' => 'Rekanan pengadaan seragam'])->assertCreated();

        $laporan = Laporan::query()->sole();
        $this->assertSame(KedudukanTerlapor::LainLain, $laporan->kedudukan_terlapor);
        $mentah = DB::table('terlapor_wbs')->where('laporan_id', $laporan->id)->orderBy('id')->first();
        $this->assertSame('Rekanan pengadaan seragam', Crypt::decryptString($mentah->kedudukan_lain));
        $this->assertNull(DB::table('terlapor_wbs')->where('laporan_id', $laporan->id)->orderByDesc('id')->value('kedudukan_lain'));
    }

    public function test_report_without_access_token_is_refused(): void
    {
        $this->kirim($this->isianSah(), tokenAkses: '')->assertJsonValidationErrors('token_akses');
        $this->kirim($this->isianSah(), tokenAkses: 'palsu.palsu')->assertJsonValidationErrors('token_akses');

        $this->assertSame(0, Laporan::query()->count());
    }

    public function test_attachments_are_stored_encrypted_and_listed_on_the_receipt(): void
    {
        Storage::fake('lampiran');
        $audio = $this->mp3Uji();
        $idAudio = $this->idUnggahanSiap($audio, 'rekaman.mp3', 'wbs');

        $response = $this->kirim([...$this->isianSah(), 'lampiran' => [$idAudio]], $this->tokenUnggahWbs);

        $response->assertCreated()->assertJsonPath('lampiran', ['rekaman.mp3']);
        $lampiran = Laporan::query()->sole()->lampiran()->sole();
        $isiTersimpan = Storage::disk('lampiran')->get($lampiran->path_acak);
        $this->assertStringStartsWith('SIGAPENC1', $isiTersimpan);
        $this->assertStringNotContainsString(substr(file_get_contents($audio), 0, 64), $isiTersimpan);
        $this->assertNotSame('rekaman.mp3', DB::table('lampiran')->value('nama_asli'));
    }

    public function test_pengaduan_uploads_cannot_be_attached_to_a_wbs_report(): void
    {
        Storage::fake('lampiran');
        $idPengaduan = $this->idUnggahanSiap($this->pdfUji(), 'bukti.pdf');

        $this->kirim([...$this->isianSah(), 'lampiran' => [$idPengaduan]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lampiran');
    }

    public function test_required_fields_and_pin_rules_are_enforced(): void
    {
        $this->kirim([...$this->isianSah(), 'kedudukan_terlapor' => '', 'terlapor' => [], 'pin' => '12345', 'pin_confirmation' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kedudukan_terlapor', 'terlapor', 'pin']);

        $this->kirim([...$this->isianSah(), 'pin_confirmation' => '582905'])->assertJsonValidationErrors('pin');
        $this->kirim([...$this->isianSah(), 'pin' => '123456', 'pin_confirmation' => '123456'])->assertJsonValidationErrors('pin');
        $this->kirim([...$this->isianSah(), 'pin' => '777777', 'pin_confirmation' => '777777'])->assertJsonValidationErrors('pin');
        $this->kirim([...$this->isianSah(), 'terlapor' => [['nama' => '', 'jabatan' => 'Kepala TU']]])->assertJsonValidationErrors('terlapor.0.nama');
        $this->kirim([...$this->isianSah(), 'kategori_id' => Kategori::factory()->create()->id])->assertJsonValidationErrors('kategori_id');

        $this->assertSame(0, Laporan::query()->count());
    }

    public function test_honeypot_and_missing_captcha_are_refused(): void
    {
        $this->kirim([...$this->isianSah(), 'situs_web' => 'https://spam.example'])->assertJsonValidationErrors('situs_web');
        $this->kirim([...$this->isianSah(), 'altcha' => ''])->assertJsonValidationErrors('altcha');

        $this->assertSame(0, Laporan::query()->count());
    }

    public function test_form_token_is_single_use(): void
    {
        $token = TokenFormulirWbs::terbitkan();

        $this->kirim($this->isianSah(), $token)->assertCreated();
        $this->kirim($this->isianSah(), $token)->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->assertSame(1, Laporan::query()->count());
    }

    public function test_expired_or_forged_form_token_is_refused(): void
    {
        $this->freezeTime();
        $kedaluwarsa = TokenFormulirWbs::terbitkan();
        $this->travel(TokenFormulirWbs::masaMenit() + 1)->minutes();

        $this->kirim($this->isianSah(), $kedaluwarsa)->assertJsonValidationErrors('token');
        $this->kirim($this->isianSah(), 'palsu.palsu')->assertJsonValidationErrors('token');
        $this->assertSame(0, Laporan::query()->count());
    }

    public function test_validation_errors_do_not_consume_the_form_token(): void
    {
        $token = TokenFormulirWbs::terbitkan();

        $this->kirim([...$this->isianSah(), 'judul' => ''], $token)->assertUnprocessable();
        $this->kirim($this->isianSah(), $token)->assertCreated();
    }

    public function test_form_token_is_refreshed_without_cookies_only_with_an_access_token(): void
    {
        $this->getJson('/wbs/lapor/token')->assertStatus(419);

        $response = $this->getJson('/wbs/lapor/token', ['X-Token-Akses' => $this->tokenAkses()]);

        $response->assertOk();
        $this->assertNotNull(TokenFormulirWbs::nonce($response->json('token')));
        $this->assertTrue(KodeAksesWbs::tokenSah($response->json('tokenAkses')));
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_receipt_pdf_downloads_once_with_a_token_that_does_not_contain_the_code(): void
    {
        $response = $this->kirim($this->isianSah());
        $kode = $response->json('kodeLacak');
        $tokenPdf = $response->json('tokenPdf');

        $this->assertStringNotContainsString($kode, $tokenPdf);
        $this->assertStringNotContainsString(str_replace('-', '', $kode), $tokenPdf);

        $pdf = $this->post('/wbs/bukti/pdf', ['token' => $tokenPdf]);
        $pdf->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame([], $pdf->headers->getCookies());
        $this->assertStringContainsString('no-store', (string) $pdf->headers->get('Cache-Control'));
        $teks = $this->teksPdf($pdf->getContent());
        $this->assertStringContainsString($kode, $teks);
        $this->assertStringContainsString('PIN tidak tercantum', $teks);
        $this->assertStringContainsString('/wbs/lacak', $teks);
        $this->assertStringNotContainsString('582904', $teks);
        $this->assertStringNotContainsString('Bendahara', $teks);

        $this->post('/wbs/bukti/pdf', ['token' => $tokenPdf])->assertStatus(410);
    }

    public function test_receipt_pdf_token_expires_after_ten_minutes(): void
    {
        $tokenPdf = $this->kirim($this->isianSah())->json('tokenPdf');

        $this->travel(11)->minutes();

        $this->post('/wbs/bukti/pdf', ['token' => $tokenPdf])->assertStatus(410);
        $this->post('/wbs/bukti/pdf', ['token' => 'tidak-sah'])->assertStatus(410);
    }

    public function test_whole_wbs_flow_sets_no_cookie_and_leaves_no_ip_in_sessions_cache_or_logs(): void
    {
        config(['session.driver' => 'database']);
        Storage::fake('lampiran');
        $catatan = [];
        Event::listen(MessageLogged::class, function (MessageLogged $pesan) use (&$catatan): void {
            $catatan[] = $pesan->message.' '.json_encode($pesan->context);
        });
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])->withHeader('User-Agent', 'AgenUjiWbs/9.9');

        $kode = $this->aturKodeAkses();
        $akses = $this->periksaKode($kode);
        $respons = [
            $this->get('/wbs'),
            $akses,
            $this->postJson('/wbs/lapor/buka', ['token_akses' => $akses->json('tokenAkses')]),
            $this->getJson('/wbs/lapor/token', ['X-Token-Akses' => $akses->json('tokenAkses')]),
            $this->unggahBertahap($this->pdfUji(), 'bukti.pdf', 'wbs'),
        ];
        $kirim = $this->kirim($this->isianSah());
        $respons[] = $kirim;
        $respons[] = $this->post('/wbs/bukti/pdf', ['token' => $kirim->json('tokenPdf')]);

        foreach ($respons as $response) {
            $this->assertSame([], $response->headers->getCookies());
            $response->assertHeader('Referrer-Policy', 'no-referrer');
            $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }

        $this->assertSame(0, DB::table('sessions')->count());
        $kunciCache = array_keys((new ReflectionProperty(Cache::store()->getStore(), 'storage'))->getValue(Cache::store()->getStore()));
        foreach ([...$catatan, ...$kunciCache] as $baris) {
            foreach (['203.0.113.77', 'AgenUjiWbs', hash('sha256', '203.0.113.77'), sha1('203.0.113.77'), md5('203.0.113.77')] as $jejak) {
                $this->assertStringNotContainsString($jejak, $baris);
            }
        }
    }

    private function periksaKode(string $kode): TestResponse
    {
        return $this->postJson('/wbs/akses', ['kode' => $kode, 'altcha' => $this->solusiAltcha()]);
    }

    /**
     * @param  array<string, mixed>  $isian
     */
    private function kirim(array $isian, ?string $token = null, ?string $tokenAkses = null): TestResponse
    {
        return $this->postJson('/wbs/lapor', [
            ...$isian,
            'token_akses' => $tokenAkses ?? $this->tokenAkses(),
            'token' => $token ?? TokenFormulirWbs::terbitkan(),
            'altcha' => array_key_exists('altcha', $isian) ? $isian['altcha'] : $this->solusiAltcha(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function isianSah(): array
    {
        return [
            'nama' => 'Andi Pratama',
            'email' => 'andi@example.com',
            'no_hp' => '0812-3456-7890',
            'kategori_id' => Kategori::factory()->wbs()->create()->id,
            'unit_id' => Unit::factory()->create()->id,
            'terlapor' => [
                ['nama' => 'Bendahara Yayasan', 'jabatan' => 'Bendahara'],
                ['nama' => 'Orang tidak dikenal', 'jabatan' => ''],
            ],
            'kedudukan_terlapor' => KedudukanTerlapor::Pengurus->value,
            'judul' => 'Dana kegiatan dipotong',
            'kronologi' => 'Dana kegiatan siswa dipotong tanpa bukti sejak awal semester ganjil.',
            'lokasi' => 'Kantor Yayasan',
            'saksi' => 'Staf tata usaha',
            'pin' => '582904',
            'pin_confirmation' => '582904',
        ];
    }
}
