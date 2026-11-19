<?php

namespace Tests\Feature\Filament\Pages\Auth;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Filament\Pages\Auth\ProfilPetugas;
use App\Models\User;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class KataSandiTest extends TestCase
{
    use RefreshDatabase;

    private const KATA_SANDI_BARU = 'KataSandiBaru-2026!';

    private function buatKataSandi(User $user, string $token): void
    {
        Livewire::test(ResetPassword::class, ['email' => $user->email, 'token' => $token])
            ->fillForm(['password' => self::KATA_SANDI_BARU, 'passwordConfirmation' => self::KATA_SANDI_BARU])
            ->call('resetPassword');
    }

    public function test_the_link_sets_the_password_once_and_is_audited_without_the_value(): void
    {
        $user = User::factory()->peran(Peran::Pembina)->create();
        $token = Password::broker()->createToken($user);

        $this->get(Filament::getPanel('panel')->getResetPasswordUrl($token, $user))->assertOk();

        $this->buatKataSandi($user, $token);

        $this->assertTrue(Hash::check(self::KATA_SANDI_BARU, $user->refresh()->password));
        $this->assertDatabaseHas('log_audit', ['aksi' => AksiAudit::MengaturKataSandiLewatTautan->value, 'objek' => "users:{$user->id}"]);
        $this->assertDatabaseCount('riwayat_pengaturan', 0);

        $user->forceFill(['password' => 'kata-sandi-lain-123'])->save();
        $this->buatKataSandi($user, $token);

        $this->assertFalse(Hash::check(self::KATA_SANDI_BARU, $user->refresh()->password), 'Tautan hanya boleh dipakai sekali.');
    }

    public function test_an_expired_link_is_refused(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->create();
        $hashLama = $user->password;
        $token = Password::broker()->createToken($user);

        $this->travel((int) config('auth.passwords.users.expire') + 1)->minutes();
        $this->buatKataSandi($user, $token);

        $this->assertSame($hashLama, $user->refresh()->password);
        $this->assertDatabaseMissing('log_audit', ['aksi' => AksiAudit::MengaturKataSandiLewatTautan->value]);
    }

    public function test_self_service_link_requests_are_disabled(): void
    {
        $this->get('/panel/password-reset/request')->assertNotFound();
        $this->get('/panel/login')->assertOk()->assertDontSee('/panel/password-reset/request');
    }

    public function test_changing_the_password_without_the_current_password_is_refused(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $hashLama = $user->password;

        $this->actingAs($user);

        Livewire::test(ProfilPetugas::class)
            ->fillForm(['password' => self::KATA_SANDI_BARU, 'passwordConfirmation' => self::KATA_SANDI_BARU])
            ->call('save')
            ->assertHasFormErrors(['currentPassword' => 'required']);

        Livewire::test(ProfilPetugas::class)
            ->fillForm(['password' => self::KATA_SANDI_BARU, 'passwordConfirmation' => self::KATA_SANDI_BARU, 'currentPassword' => 'salah-sekali'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $this->assertSame($hashLama, $user->refresh()->password);
        $this->assertDatabaseMissing('log_audit', ['aksi' => AksiAudit::MengubahKataSandi->value]);
    }

    public function test_an_officer_changes_their_own_password_and_it_is_audited_without_the_value(): void
    {
        $user = User::factory()->peran(Peran::PicUnit)->denganMfa()->create(['email' => 'pic@contoh.test']);

        $this->actingAs($user)->get('/panel/profile')->assertOk();

        Livewire::test(ProfilPetugas::class)
            ->fillForm([
                'email' => 'ganti@contoh.test',
                'password' => self::KATA_SANDI_BARU,
                'passwordConfirmation' => self::KATA_SANDI_BARU,
                'currentPassword' => 'password',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertTrue(Hash::check(self::KATA_SANDI_BARU, $user->password));
        $this->assertSame('pic@contoh.test', $user->email, 'Surel dikelola di Pengaturan, bukan di profil.');
        $this->assertDatabaseHas('log_audit', ['user_id' => $user->id, 'aksi' => AksiAudit::MengubahKataSandi->value, 'objek' => "users:{$user->id}"]);
        $this->assertDatabaseCount('riwayat_pengaturan', 0);
    }

    public function test_sso_accounts_have_no_password_fields_and_nobody_changes_2fa_on_the_profile(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->denganMfa()->create();

        $this->actingAs($admin);

        Livewire::test(ProfilPetugas::class)
            ->assertFormFieldDoesNotExist('password')
            ->assertFormFieldDoesNotExist('currentPassword');

        $this->assertNull((new ProfilPetugas)->getMultiFactorAuthenticationContentComponent());
    }
}
