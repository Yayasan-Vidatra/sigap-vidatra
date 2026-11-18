<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\JenisLaporan;
use App\Services\Lampiran\UnggahanBertahap;
use App\Services\Wbs\TokenFormulirWbs;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MembuatBerkasUji;
use Tests\Concerns\MengunggahLampiran;
use Tests\TestCase;

/**
 * Unggah lampiran WBS lewat /wbs/lampiran/unggah: tanpa sesi dan cookie, wajib token formulir WBS, dan
 * pembatas lajunya global serta per token, bukan per IP.
 */
class UnggahLampiranWbsTest extends TestCase
{
    use MembuatBerkasUji;
    use MengunggahLampiran;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('lampiran');
    }

    public function test_audio_evidence_is_uploaded_through_the_wbs_path(): void
    {
        $hasil = $this->unggahBertahap($this->mp3Uji(), 'rekaman.mp3', 'wbs');

        $hasil->assertOk()->assertJson(['jenis' => 'audio']);
        $this->assertSame([], $hasil->headers->getCookies());
        $this->assertNotNull(app(UnggahanBertahap::class)->ambilSiap($hasil->json('id'), JenisLaporan::Wbs));
    }

    public function test_upload_without_a_valid_form_token_is_refused(): void
    {
        $badan = ['nama' => 'bukti.pdf', 'ukuran' => 100, 'kanal' => 'wbs'];

        $this->postJson('/wbs/lampiran/unggah', $badan)->assertStatus(419);
        $this->postJson('/wbs/lampiran/unggah', $badan, ['X-Token-Formulir' => 'palsu.palsu'])->assertStatus(419);

        $terpakai = TokenFormulirWbs::terbitkan();
        TokenFormulirWbs::pakai($terpakai);
        $this->postJson('/wbs/lampiran/unggah', $badan, ['X-Token-Formulir' => $terpakai])->assertStatus(419);
    }

    public function test_expired_form_token_is_refused(): void
    {
        $this->freezeTime();
        $this->tokenUnggahWbs = TokenFormulirWbs::terbitkan();
        $this->travel(TokenFormulirWbs::masaMenit() + 1)->minutes();

        $this->postJson('/wbs/lampiran/unggah', ['nama' => 'bukti.pdf', 'ukuran' => 100, 'kanal' => 'wbs'], $this->headerUnggah('wbs'))
            ->assertStatus(419);
    }

    public function test_wbs_uploads_cannot_go_through_the_session_path(): void
    {
        $this->postJson('/lampiran/unggah', ['nama' => 'rekaman.mp3', 'ukuran' => 100, 'kanal' => 'wbs'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kanal');
    }

    public function test_upload_of_one_channel_cannot_be_finished_through_the_other(): void
    {
        $sumber = $this->pdfUji();
        $wbs = $this->postJson('/wbs/lampiran/unggah', ['nama' => 'a.pdf', 'ukuran' => filesize($sumber), 'kanal' => 'wbs'], $this->headerUnggah('wbs'))->json('id');
        $pengaduan = $this->postJson('/lampiran/unggah', ['nama' => 'a.pdf', 'ukuran' => filesize($sumber), 'kanal' => 'pengaduan'])->json('id');

        $this->kirimPotongan($wbs, 0, $sumber, 'pengaduan')->assertUnprocessable();
        $this->postJson("/lampiran/unggah/{$wbs}/selesai")->assertUnprocessable();
        $this->kirimPotongan($pengaduan, 0, $sumber, 'wbs')->assertUnprocessable();
        $this->postJson("/wbs/lampiran/unggah/{$pengaduan}/selesai", [], $this->headerUnggah('wbs'))->assertUnprocessable();
    }

    public function test_global_limit_applies_across_ip_addresses(): void
    {
        config(['sigap.wbs.batas_unggah_global_per_menit' => 2, 'sigap.wbs.batas_unggah_per_token_per_menit' => 100]);
        $badan = ['nama' => 'bukti.pdf', 'ukuran' => 100, 'kanal' => 'wbs'];

        foreach (['198.51.100.1', '198.51.100.2'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/wbs/lampiran/unggah', $badan, ['X-Token-Formulir' => TokenFormulirWbs::terbitkan()])
                ->assertCreated();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.3'])
            ->postJson('/wbs/lampiran/unggah', $badan, ['X-Token-Formulir' => TokenFormulirWbs::terbitkan()])
            ->assertTooManyRequests();
    }

    public function test_per_token_limit_does_not_block_other_forms(): void
    {
        config(['sigap.wbs.batas_unggah_global_per_menit' => 100, 'sigap.wbs.batas_unggah_per_token_per_menit' => 1]);
        $badan = ['nama' => 'bukti.pdf', 'ukuran' => 100, 'kanal' => 'wbs'];
        $token = TokenFormulirWbs::terbitkan();

        $this->postJson('/wbs/lampiran/unggah', $badan, ['X-Token-Formulir' => $token])->assertCreated();
        $this->postJson('/wbs/lampiran/unggah', $badan, ['X-Token-Formulir' => $token])->assertTooManyRequests();
        $this->postJson('/wbs/lampiran/unggah', $badan, ['X-Token-Formulir' => TokenFormulirWbs::terbitkan()])->assertCreated();
    }
}
