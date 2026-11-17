<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Enums\AksiAudit;
use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as PenggunaGoogle;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginGoogleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_sends_the_officer_to_google(): void
    {
        Socialite::fake('google');

        $response = $this->get(route('filament.panel.sso.google.redirect'));

        $response->assertRedirect('https://socialite.fake/google/authorize');
    }

    public function test_registered_sso_admin_with_2fa_must_verify_before_signing_in(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->denganMfa()->create();
        $this->googleMengembalikan($admin->email);

        $response = $this->get(route('filament.panel.sso.google.callback'));

        $response->assertRedirect(route('filament.panel.sso.google.verifikasi'));
        $this->assertGuest();
        $this->assertSame($admin->id, session('sigap.sso_menunggu_2fa.id'));
    }

    public function test_registered_sso_pic_without_2fa_signs_in_and_must_set_it_up(): void
    {
        $pic = User::factory()->peran(Peran::PicUnit)->sso()->create();
        $this->googleMengembalikan($pic->email);

        $response = $this->get(route('filament.panel.sso.google.callback'));

        $response->assertRedirect('/panel');
        $this->assertAuthenticatedAs($pic);
        $this->get('/panel')->assertRedirect('/panel/multi-factor-authentication/set-up');
    }

    public function test_unregistered_google_account_is_refused_and_no_account_is_created(): void
    {
        $this->googleMengembalikan('baru@vidatra.sch.id');

        $response = $this->get(route('filament.panel.sso.google.callback'));

        $response->assertRedirect('/panel/login');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('log_audit', [
            'user_id' => null,
            'aksi' => AksiAudit::LoginSsoDitolak->value,
            'objek' => LogAudit::OBJEK_PENGGUNA_TIDAK_TERDAFTAR,
        ]);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function akunLuarDomain(): array
    {
        return [
            'surel domain lain' => ['admin@gmail.com', ['hd' => null]],
            'surel yayasan tanpa klaim hd' => ['admin@vidatra.sch.id', ['hd' => null]],
            'klaim hd tenant lain' => ['admin@vidatra.sch.id', ['hd' => 'sekolah-lain.sch.id']],
            'surel belum terverifikasi' => ['admin@vidatra.sch.id', ['email_verified' => false]],
        ];
    }

    /**
     * @param  array<string, mixed>  $klaim
     */
    #[DataProvider('akunLuarDomain')]
    public function test_google_account_outside_the_yayasan_workspace_is_refused(string $email, array $klaim): void
    {
        User::factory()->peran(Peran::AdminPengaduan)->sso()->denganMfa()->create(['email' => 'admin@vidatra.sch.id']);
        $this->googleMengembalikan($email, $klaim);

        $response = $this->get(route('filament.panel.sso.google.callback'));

        $response->assertRedirect('/panel/login');
        $this->assertGuest();
        $this->assertNull(session('sigap.sso_menunggu_2fa'));
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranAkunLokal(): array
    {
        return [
            'ketua tim wbs' => [Peran::KetuaTimWbs],
            'dewan pengawas' => [Peran::DewanPengawas],
            'kepala sekolah' => [Peran::KepalaSekolah],
            'super admin' => [Peran::SuperAdmin],
        ];
    }

    #[DataProvider('peranAkunLokal')]
    public function test_local_only_peran_cannot_sign_in_with_google(Peran $peran): void
    {
        $user = User::factory()->peran($peran)->denganMfa()->create(['email' => 'petugas@vidatra.sch.id']);
        $this->googleMengembalikan($user->email);

        $response = $this->get(route('filament.panel.sso.google.callback'));

        $response->assertRedirect('/panel/login');
        $this->assertGuest();
        $this->assertDatabaseHas('log_audit', ['user_id' => $user->id, 'aksi' => AksiAudit::LoginSsoDitolak->value]);
    }

    public function test_admin_with_a_local_account_cannot_sign_in_with_google(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create([
            'email' => 'admin@vidatra.sch.id',
            'jenis_akun' => JenisAkun::Lokal,
        ]);
        $this->googleMengembalikan($admin->email);

        $this->get(route('filament.panel.sso.google.callback'))->assertRedirect('/panel/login');

        $this->assertGuest();
    }

    public function test_locked_sso_account_cannot_sign_in_with_google(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->denganMfa()->terkunci()->create();
        $this->googleMengembalikan($admin->email);

        $this->get(route('filament.panel.sso.google.callback'))->assertRedirect('/panel/login');

        $this->assertGuest();
        $this->assertNull(session('sigap.sso_menunggu_2fa'));
    }

    /**
     * @param  array<string, mixed>  $klaim
     */
    private function googleMengembalikan(string $email, array $klaim = []): void
    {
        Socialite::fake('google', PenggunaGoogle::fake([
            'email' => $email,
            'hd' => 'vidatra.sch.id',
            'email_verified' => true,
            ...$klaim,
        ]));
    }
}
