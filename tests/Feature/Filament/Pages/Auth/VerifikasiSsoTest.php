<?php

namespace Tests\Feature\Filament\Pages\Auth;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Filament\Pages\Auth\VerifikasiSso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class VerifikasiSsoTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_without_a_pending_google_login_returns_to_login(): void
    {
        Livewire::test(VerifikasiSso::class)->assertRedirect('/panel/login');

        $this->assertGuest();
    }

    public function test_correct_authenticator_code_completes_the_google_login(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->denganMfa()->create();
        VerifikasiSso::tandaiMenunggu($admin);

        Livewire::test(VerifikasiSso::class)
            ->set('data.app.code', (new Google2FA)->getCurrentOtp($admin->app_authentication_secret))
            ->call('verifikasi')
            ->assertHasNoErrors()
            ->assertRedirect('/panel');

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('sigap.sso_menunggu_2fa'));
        $this->assertDatabaseHas('log_audit', ['user_id' => $admin->id, 'aksi' => AksiAudit::LoginBerhasil->value]);
    }

    public function test_wrong_authenticator_code_keeps_the_officer_signed_out(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->denganMfa()->create();
        VerifikasiSso::tandaiMenunggu($admin);

        Livewire::test(VerifikasiSso::class)
            ->set('data.app.code', '000000')
            ->call('verifikasi')
            ->assertHasErrors(['data.app.code']);

        $this->assertGuest();
    }

    public function test_expired_pending_login_is_refused(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->denganMfa()->create();
        VerifikasiSso::tandaiMenunggu($admin);
        $this->travel(6)->minutes();

        Livewire::test(VerifikasiSso::class)->assertRedirect('/panel/login');

        $this->assertGuest();
    }

    public function test_account_locked_during_verification_is_refused(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->denganMfa()->create();
        VerifikasiSso::tandaiMenunggu($admin);
        $halaman = Livewire::test(VerifikasiSso::class);
        $admin->forceFill(['terkunci_pada' => now()])->save();

        $halaman
            ->set('data.app.code', (new Google2FA)->getCurrentOtp($admin->app_authentication_secret))
            ->call('verifikasi')
            ->assertRedirect('/panel/login');

        $this->assertGuest();
    }
}
