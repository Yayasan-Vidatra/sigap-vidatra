<?php

namespace Tests\Feature\Rules;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\Solution;
use App\Enums\JenisPelapor;
use App\Models\Kategori;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MemecahkanAltcha;
use Tests\TestCase;

/**
 * AltchaSah lewat Formulir Pengaduan: solusi wajib ada, sah, belum kedaluwarsa, dan hanya sekali pakai.
 */
class AltchaSahTest extends TestCase
{
    use MemecahkanAltcha;
    use RefreshDatabase;

    public function test_valid_solution_is_accepted(): void
    {
        $response = $this->post('/pengaduan', $this->isianPengaduan($this->solusiAltcha()));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('bukti-lapor'));
        $this->assertDatabaseCount('laporan', 1);
    }

    public function test_submission_without_a_solution_is_rejected(): void
    {
        $response = $this->post('/pengaduan', $this->isianPengaduan(''));

        $response->assertSessionHasErrors(['altcha' => 'Selesaikan verifikasi "Saya bukan robot" terlebih dahulu.']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_garbage_payload_is_rejected(): void
    {
        $response = $this->post('/pengaduan', $this->isianPengaduan(base64_encode('{"bukan":"payload"}')));

        $response->assertSessionHasErrors(['altcha' => 'Verifikasi "Saya bukan robot" tidak sah. Silakan ulangi.']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_wrong_solution_to_a_real_challenge_is_rejected(): void
    {
        $tantangan = $this->tantanganAltcha();
        $palsu = (new Payload($tantangan, new Solution(counter: 999999, derivedKey: str_repeat('ab', 32))))->toBase64();

        $response = $this->post('/pengaduan', $this->isianPengaduan($palsu));

        $response->assertSessionHasErrors(['altcha' => 'Verifikasi "Saya bukan robot" tidak sah. Silakan ulangi.']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_challenge_signed_with_another_key_is_rejected(): void
    {
        $tantanganLuar = (new Altcha(hmacSignatureSecret: 'kunci-penyerang'))->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2,
            cost: 1,
            counter: 3,
            expiresAt: time() + 600,
        ));

        $response = $this->post('/pengaduan', $this->isianPengaduan($this->solusiAltcha($tantanganLuar)));

        $response->assertSessionHasErrors(['altcha' => 'Verifikasi "Saya bukan robot" tidak sah. Silakan ulangi.']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_expired_solution_is_rejected(): void
    {
        $kedaluwarsa = $this->solusiAltcha($this->tantanganAltcha(masaBerlakuDetik: -1));

        $response = $this->post('/pengaduan', $this->isianPengaduan($kedaluwarsa));

        $response->assertSessionHasErrors(['altcha' => 'Verifikasi "Saya bukan robot" sudah kedaluwarsa. Silakan ulangi.']);
        $this->assertDatabaseCount('laporan', 0);
    }

    public function test_solution_used_twice_is_rejected_the_second_time(): void
    {
        $solusi = $this->solusiAltcha();
        $this->post('/pengaduan', $this->isianPengaduan($solusi))->assertSessionHasNoErrors();

        $response = $this->post('/pengaduan', $this->isianPengaduan($solusi));

        $response->assertSessionHasErrors(['altcha' => 'Verifikasi "Saya bukan robot" sudah dipakai. Silakan ulangi.']);
        $this->assertDatabaseCount('laporan', 1);
    }

    public function test_solution_stays_usable_after_other_fields_fail_validation(): void
    {
        $solusi = $this->solusiAltcha();
        $this->post('/pengaduan', $this->isianPengaduan($solusi, ['judul' => '']))->assertSessionHasErrors(['judul']);

        $response = $this->post('/pengaduan', $this->isianPengaduan($solusi));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('laporan', 1);
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function isianPengaduan(string $altcha, array $ubah = []): array
    {
        return [
            'unit_id' => Unit::factory()->create()->id,
            'kategori_id' => Kategori::factory()->create()->id,
            'jenis_pelapor' => JenisPelapor::OrangTua->value,
            'nama' => 'Rina Kartika',
            'email' => 'rina@example.com',
            'judul' => 'Toilet lantai 2 rusak',
            'kronologi' => 'Keran di toilet lantai 2 bocor sejak hari Senin.',
            'setuju_ketentuan' => true,
            'altcha' => $altcha,
            ...$ubah,
        ];
    }
}
