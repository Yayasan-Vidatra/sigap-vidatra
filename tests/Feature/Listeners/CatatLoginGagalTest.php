<?php

namespace Tests\Feature\Listeners;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Filament\Pages\Auth\Login;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CatatLoginGagalTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_wrong_passwords_lock_the_account_and_are_logged(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create(['password' => 'rahasia-panjang']);

        foreach (range(1, 5) as $percobaan) {
            $this->cobaLogin($user->email, 'salah-'.$percobaan);
        }

        $this->assertTrue($user->fresh()->terkunci());
        $this->assertSame(5, LogAudit::query()->where('aksi', AksiAudit::LoginGagal->value)->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('log_audit', [
            'user_id' => $user->id,
            'aksi' => AksiAudit::AkunTerkunci->value,
            'objek' => "users:{$user->id}",
        ]);
    }

    public function test_locked_account_is_refused_even_with_the_correct_password(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->terkunci()->create(['password' => 'rahasia-panjang']);

        $this->cobaLogin($user->email, 'rahasia-panjang')
            ->assertHasErrors(['data.email'])
            ->assertSet('userUndertakingMultiFactorAuthentication', null);

        $this->assertGuest();
        $this->assertSame(5, $user->fresh()->gagal_login);
        $this->assertDatabaseHas('log_audit', ['user_id' => $user->id, 'aksi' => AksiAudit::LoginDitolakAkunTerkunci->value]);
    }

    public function test_failed_login_for_an_unknown_email_is_logged_without_storing_the_email(): void
    {
        $this->cobaLogin('orang.asing@example.com', 'apa-saja');

        $log = LogAudit::query()->sole();

        $this->assertNull($log->user_id);
        $this->assertSame(AksiAudit::LoginGagal->value, $log->aksi);
        $this->assertSame(LogAudit::OBJEK_PENGGUNA_TIDAK_TERDAFTAR, $log->objek);
    }

    private function cobaLogin(string $email, string $password): Testable
    {
        return Livewire::test(Login::class)
            ->fillForm(['email' => $email, 'password' => $password])
            ->call('authenticate');
    }
}
