<?php

namespace Tests\Feature\Filament\Pages\Auth;

use App\Enums\Peran;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_account_must_pass_the_authenticator_code_before_signing_in(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create(['password' => 'rahasia-panjang']);

        $halaman = Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'rahasia-panjang'])
            ->call('authenticate');

        $this->assertGuest();
        $this->assertNotNull($halaman->get('userUndertakingMultiFactorAuthentication'));

        $halaman
            ->set('data.multiFactor.app.code', (new Google2FA)->getCurrentOtp($user->app_authentication_secret))
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_authenticator_code_does_not_sign_in(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create(['password' => 'rahasia-panjang']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'rahasia-panjang'])
            ->call('authenticate')
            ->set('data.multiFactor.app.code', '000000')
            ->call('authenticate')
            ->assertHasErrors(['data.multiFactor.app.code']);

        $this->assertGuest();
    }

    public function test_sso_account_cannot_sign_in_with_a_password(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->denganMfa()->create(['password' => 'rahasia-panjang']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'rahasia-panjang'])
            ->call('authenticate')
            ->assertHasErrors(['data.email'])
            ->assertSet('userUndertakingMultiFactorAuthentication', null);

        $this->assertGuest();
    }

    public function test_wrong_password_shows_the_generic_message(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create(['password' => 'rahasia-panjang']);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'salah'])
            ->call('authenticate')
            ->assertSee('Akun terkunci setelah 5 kali gagal');

        $this->assertGuest();
    }

    public function test_officer_without_2fa_is_sent_to_set_it_up(): void
    {
        $user = User::factory()->peran(Peran::AdminPengaduan)->create();

        $response = $this->actingAs($user)->get('/panel');

        $response->assertRedirect('/panel/multi-factor-authentication/set-up');
    }
}
