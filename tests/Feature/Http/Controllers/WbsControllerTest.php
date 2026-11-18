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
use App\Services\Wbs\TokenFormulirWbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MembacaPdf;
use Tests\Concerns\MembuatBerkasUji;
use Tests\Concerns\MemecahkanAltcha;
use Tests\Concerns\MengunggahLampiran;
use Tests\TestCase;

/**
 * Kanal WBS: H-03 Jaminan Kerahasiaan, H-04 Formulir WBS, dan H-05 Bukti Lapor WBS (US-05, US-06, US-07).
 */
class WbsControllerTest extends TestCase
{
    use MembacaPdf;
    use MembuatBerkasUji;
    use MemecahkanAltcha;
    use MengunggahLampiran;
    use RefreshDatabase;

    public function test_assurance_page_shows_default_text_until_a_document_is_configured(): void
    {
        $response = $this->get('/wbs');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Wbs/Jaminan')
            ->where('berkasJaminan', null)
            ->where('saluran', ['surel' => null, 'telepon' => null, 'surat' => null]));
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_assurance_page_reads_the_document_and_alternative_channels_from_settings(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pengaturan/jaminan.pdf', file_get_contents($this->pdfUji()));
        Pengaturan::factory()->create(['kunci' => Pengaturan::WBS_BERKAS_JAMINAN, 'nilai' => 'pengaturan/jaminan.pdf']);
        Pengaturan::factory()->create(['kunci' => Pengaturan::WBS_SALURAN_SUREL, 'nilai' => 'wbs@vidatra.sch.id']);
        Pengaturan::factory()->create(['kunci' => Pengaturan::WBS_SALURAN_TELEPON, 'nilai' => '0811-0000-0000']);

        $this->get('/wbs')->assertInertia(fn (Assert $page) => $page
            ->where('berkasJaminan', route('wbs.jaminan.berkas'))
            ->where('saluran.surel', 'wbs@vidatra.sch.id')
            ->where('saluran.telepon', '0811-0000-0000')
            ->where('saluran.surat', null));

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

        $this->get('/wbs')->assertInertia(fn (Assert $page) => $page->where('berkasJaminan', null));
        $this->get('/wbs/jaminan-kerahasiaan')->assertNotFound();
    }

    public function test_form_opens_only_after_the_assurance_button(): void
    {
        $this->get('/wbs/lapor')->assertRedirect(route('wbs'));
    }

    public function test_form_offers_wbs_categories_active_units_and_every_matrix_position(): void
    {
        $unit = Unit::factory()->create(['nama' => 'SMA YPVDP', 'kontak_darurat' => '0812-1111-2222']);
        Unit::factory()->nonaktif()->create();
        $kategori = Kategori::factory()->wbs()->create(['nama' => 'Suap atau gratifikasi']);
        Kategori::factory()->create();

        $response = $this->get('/wbs/lapor?mengerti=1');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Wbs/Formulir')
            ->where('unit', [['id' => $unit->id, 'nama' => 'SMA YPVDP']])
            ->where('kategori', [['id' => $kategori->id, 'nama' => 'Suap atau gratifikasi']])
            ->has('kedudukan', count(KedudukanTerlapor::cases()))
            ->where('kedudukan.5', ['value' => 'tidak_tahu', 'label' => 'Tidak tahu'])
            ->where('kontakDarurat', [['unit' => 'SMA YPVDP', 'kontak' => '0812-1111-2222']])
            ->where('token', fn (string $token): bool => TokenFormulirWbs::nonce($token) !== null));
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_anonymous_report_is_stored_encrypted_without_identity(): void
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
        $this->assertNull($laporan->identitasPelapor);
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

    public function test_named_report_stores_identity_encrypted_without_parent_contact(): void
    {
        $this->kirim([
            ...$this->isianSah(),
            'mode' => 'bernama',
            'nama' => 'Andi Pratama',
            'email' => 'andi@example.com',
            'no_hp' => '0812-3456-7890',
        ])->assertCreated();

        $identitas = DB::table('identitas_pelapor')->sole();
        $this->assertSame('Andi Pratama', Crypt::decryptString($identitas->nama));
        $this->assertSame('andi@example.com', Crypt::decryptString($identitas->email));
        $this->assertSame('0812-3456-7890', Crypt::decryptString($identitas->no_hp));
        $this->assertNull($identitas->kontak_orang_tua);
        $this->assertStringNotContainsString('Andi', $identitas->nama);
    }

    public function test_identity_fields_are_ignored_in_anonymous_mode(): void
    {
        $this->kirim([...$this->isianSah(), 'nama' => 'Andi Pratama', 'email' => 'andi@example.com'])->assertCreated();

        $this->assertSame(0, DB::table('identitas_pelapor')->count());
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
        $this->kirim([...$this->isianSah(), 'mode' => 'bernama'])->assertJsonValidationErrors('nama');
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

    public function test_form_token_can_be_refreshed_without_cookies(): void
    {
        $response = $this->getJson('/wbs/lapor/token');

        $response->assertOk();
        $this->assertNotNull(TokenFormulirWbs::nonce($response->json('token')));
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

    public function test_whole_wbs_flow_sets_no_cookie_and_leaves_no_session_row(): void
    {
        config(['session.driver' => 'database']);
        Storage::fake('lampiran');

        $respons = [
            $this->get('/wbs'),
            $this->get('/wbs/lapor?mengerti=1'),
            $this->getJson('/wbs/lapor/token'),
            $this->unggahBertahap($this->pdfUji(), 'bukti.pdf', 'wbs'),
        ];
        $kirim = $this->kirim($this->isianSah());
        $respons[] = $kirim;
        $respons[] = $this->post('/wbs/bukti/pdf', ['token' => $kirim->json('tokenPdf')]);

        foreach ($respons as $response) {
            $this->assertSame([], $response->headers->getCookies());
            $response->assertHeader('Referrer-Policy', 'no-referrer');
        }

        $this->assertSame(0, DB::table('sessions')->count());
    }

    /**
     * @param  array<string, mixed>  $isian
     */
    private function kirim(array $isian, ?string $token = null): TestResponse
    {
        return $this->postJson('/wbs/lapor', [
            ...$isian,
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
            'mode' => 'anonim',
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
