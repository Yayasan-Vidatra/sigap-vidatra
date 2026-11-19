<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\CatatDetakAntrean;
use App\Services\Pemantauan\PemeriksaKesehatan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KesehatanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthy_when_scheduler_and_queue_beat_recently(): void
    {
        config(['sigap.pemantauan.min_sisa_disk_persen' => 0]);
        Artisan::call('sigap:detak');

        $this->get('/kesehatan')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'cek' => ['basis_data' => true, 'scheduler' => true, 'antrean' => true, 'disk' => true]])
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertCookieMissing(config('session.cookie'));
    }

    public function test_a_stopped_queue_worker_is_reported(): void
    {
        config(['sigap.pemantauan.min_sisa_disk_persen' => 0]);
        Queue::fake();

        Artisan::call('sigap:detak');

        Queue::assertPushed(CatatDetakAntrean::class);
        $this->get('/kesehatan')->assertStatus(503)->assertJsonPath('cek.antrean', false)->assertJsonPath('cek.scheduler', true);
    }

    public function test_a_stopped_scheduler_is_reported_after_the_allowed_gap(): void
    {
        config(['sigap.pemantauan.min_sisa_disk_persen' => 0]);
        Artisan::call('sigap:detak');

        $this->travel((int) config('sigap.pemantauan.maks_jeda_antrean_menit') + 1)->minutes();

        $this->get('/kesehatan')->assertStatus(503)
            ->assertJsonPath('status', 'gagal')
            ->assertJsonPath('cek.scheduler', false)
            ->assertJsonPath('cek.antrean', false);
    }

    public function test_low_disk_space_is_reported(): void
    {
        config(['sigap.pemantauan.min_sisa_disk_persen' => 101]);
        PemeriksaKesehatan::catatDetak(PemeriksaKesehatan::KUNCI_SCHEDULER);
        PemeriksaKesehatan::catatDetak(PemeriksaKesehatan::KUNCI_ANTREAN);

        $this->get('/kesehatan')->assertStatus(503)->assertJsonPath('cek.disk', false);
    }

    public function test_the_alert_command_mails_the_it_contacts_and_fails_without_them(): void
    {
        Mail::fake();

        config(['sigap.pemantauan.surel_pengelola_ti' => []]);
        $this->artisan('sigap:peringatan-pemantauan', ['pesan' => 'antrean berhenti'])->assertFailed();

        config(['sigap.pemantauan.surel_pengelola_ti' => ['ti@contoh.test']]);
        $this->artisan('sigap:peringatan-pemantauan', ['pesan' => 'antrean berhenti'])->assertSuccessful();
    }
}
