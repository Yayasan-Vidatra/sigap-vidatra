<?php

namespace Tests\Feature\Listeners;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class CatatLoginBerhasilTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_logged_and_clears_earlier_failures(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create([
            'password' => 'rahasia-panjang',
            'gagal_login' => 3,
        ]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'rahasia-panjang'])
            ->call('authenticate')
            ->set('data.multiFactor.app.code', (new Google2FA)->getCurrentOtp($user->app_authentication_secret))
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->fresh()->gagal_login);
        $this->assertDatabaseHas('log_audit', [
            'user_id' => $user->id,
            'aksi' => AksiAudit::LoginBerhasil->value,
            'objek' => "users:{$user->id}",
        ]);
    }
}
