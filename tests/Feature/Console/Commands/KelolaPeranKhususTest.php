<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Mail\PemberitahuanPeranKhusus;
use App\Mail\TautanBuatKataSandi;
use App\Models\LogAudit;
use App\Models\RiwayatPengaturan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KelolaPeranKhususTest extends TestCase
{
    use RefreshDatabase;

    private const DASAR = ['--nomor-persetujuan' => '012/DP/XI/2026', '--tanggal-persetujuan' => '2026-11-18'];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_granting_a_wbs_role_is_audited_with_the_approval_and_the_holder_is_notified(): void
    {
        $user = User::factory()->create(['peran' => null, 'email' => 'spi@contoh.test']);

        $this->artisan('sigap:peran-khusus', ['aksi' => 'beri', 'email' => 'spi@contoh.test', 'peran' => 'ketua_tim_wbs', ...self::DASAR])
            ->assertSuccessful();

        $this->assertSame(Peran::KetuaTimWbs, $user->refresh()->peran);

        $log = LogAudit::query()->where('aksi', AksiAudit::MemberiPeranKhusus->value)->sole();
        $this->assertSame("users:{$user->id}", $log->objek);
        $riwayat = $log->riwayatPengaturan()->pluck('nilai_baru', 'kolom')->all();
        $this->assertSame('ketua_tim_wbs', $riwayat['peran']);
        $this->assertSame('Persetujuan Ketua Dewan Pengawas nomor 012/DP/XI/2026 tanggal 2026-11-18', $riwayat['dasar_persetujuan']);
        $this->assertStringStartsWith('artisan oleh ', $riwayat['pelaksana']);

        Mail::assertQueued(PemberitahuanPeranKhusus::class, function (PemberitahuanPeranKhusus $surel) use ($user): bool {
            $isi = $surel->render();

            return $surel->hasTo($user->email)
                && str_contains($isi, 'Ketua Tim WBS (SPI)')
                && str_contains($isi, '012/DP/XI/2026');
        });
    }

    public function test_a_new_account_gets_a_password_link_and_its_random_password_is_never_shown_or_recorded(): void
    {
        Log::spy();
        Str::createRandomStringsUsing(fn (int $panjang): string => substr(str_repeat('RahasiaAcakUji9', 10), 0, $panjang));

        $kode = Artisan::call('sigap:peran-khusus', ['aksi' => 'beri', 'email' => 'pengawas@contoh.test', 'peran' => 'dewan_pengawas', '--nama' => 'Ibu Pengawas', ...self::DASAR]);
        $keluaran = Artisan::output();
        $kataSandi = substr(str_repeat('RahasiaAcakUji9', 10), 0, 64);
        Str::createRandomStringsNormally();

        $this->assertSame(0, $kode);
        $pengguna = User::query()->where('email', 'pengawas@contoh.test')->sole();
        $this->assertSame(Peran::DewanPengawas, $pengguna->peran);
        $this->assertTrue(Hash::check($kataSandi, $pengguna->password));

        $this->assertStringContainsString('Tautan dikirim ke pengawas@contoh.test', $keluaran);
        $this->assertStringNotContainsString('RahasiaAcakUji9', $keluaran);
        $this->assertStringNotContainsString('Kata sandi', $keluaran);

        foreach (RiwayatPengaturan::query()->get() as $riwayat) {
            $this->assertStringNotContainsString('RahasiaAcakUji9', (string) $riwayat->nilai_baru);
            $this->assertStringNotContainsString($pengguna->password, (string) $riwayat->nilai_baru);
        }
        $this->assertSame([], LogAudit::query()->where('objek', 'like', '%RahasiaAcakUji9%')->pluck('id')->all());
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
        $this->assertDatabaseHas('log_audit', ['aksi' => AksiAudit::MengirimTautanKataSandi->value, 'objek' => "users:{$pengguna->id}"]);

        Mail::assertSent(TautanBuatKataSandi::class, function (TautanBuatKataSandi $surel) use ($pengguna): bool {
            return $surel->hasTo($pengguna->email)
                && str_contains($surel->tautan, '/panel/password-reset/reset')
                && ! str_contains($surel->render(), 'RahasiaAcakUji9');
        });
    }

    public function test_the_password_link_can_be_resent_with_an_approval(): void
    {
        $user = User::factory()->peran(Peran::Pembina)->create();

        $this->artisan('sigap:peran-khusus', ['aksi' => 'kirim-tautan', 'email' => $user->email])->assertFailed();
        Mail::assertNothingSent();

        $this->artisan('sigap:peran-khusus', ['aksi' => 'kirim-tautan', 'email' => $user->email, ...self::DASAR])
            ->expectsOutputToContain("Tautan dikirim ke {$user->email}")
            ->assertSuccessful();
        Mail::assertSent(TautanBuatKataSandi::class, fn (TautanBuatKataSandi $surel): bool => $surel->hasTo($user->email));
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function dasarTidakSah(): array
    {
        return [
            'tanpa dasar' => [[]],
            'tanpa nomor' => [['--tanggal-persetujuan' => '2026-11-18']],
            'tanpa tanggal' => [['--nomor-persetujuan' => '012/DP/XI/2026']],
            'tanggal keliru' => [['--nomor-persetujuan' => '012/DP/XI/2026', '--tanggal-persetujuan' => '18-11-2026']],
            'tanggal masa depan' => [['--nomor-persetujuan' => '012/DP/XI/2026', '--tanggal-persetujuan' => '2999-01-01']],
        ];
    }

    #[DataProvider('dasarTidakSah')]
    public function test_every_action_is_refused_without_a_valid_approval(array $dasar): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();

        foreach (['beri' => 'dewan_pengawas', 'cabut' => null, 'atur-ulang-2fa' => null] as $aksi => $peran) {
            $this->artisan('sigap:peran-khusus', array_filter(['aksi' => $aksi, 'email' => $user->email, 'peran' => $peran, ...$dasar]))
                ->assertFailed();
        }

        $user->refresh();
        $this->assertSame(Peran::KetuaTimWbs, $user->peran);
        $this->assertNotNull($user->app_authentication_secret);
        $this->assertDatabaseCount('log_audit', 0);
        Mail::assertNothingQueued();
    }

    public function test_an_sso_account_is_refused(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->create();

        $this->artisan('sigap:peran-khusus', ['aksi' => 'beri', 'email' => $admin->email, 'peran' => 'ketua_tim_wbs', ...self::DASAR])
            ->expectsOutputToContain('hanya untuk akun lokal')
            ->assertFailed();

        $this->assertSame(Peran::AdminPengaduan, $admin->refresh()->peran);
        $this->assertDatabaseCount('log_audit', 0);
        Mail::assertNothingQueued();
    }

    public function test_roles_managed_in_settings_cannot_be_granted_here(): void
    {
        $user = User::factory()->create(['peran' => null]);

        $this->artisan('sigap:peran-khusus', ['aksi' => 'beri', 'email' => $user->email, 'peran' => 'admin_pengaduan', ...self::DASAR])
            ->assertFailed();

        $this->assertNull($user->refresh()->peran);
    }

    public function test_revoking_removes_the_role_and_notifies_the_holder(): void
    {
        $user = User::factory()->peran(Peran::AnggotaTimWbs)->denganMfa()->create();

        $this->artisan('sigap:peran-khusus', ['aksi' => 'cabut', 'email' => $user->email, ...self::DASAR])->assertSuccessful();

        $this->assertNull($user->refresh()->peran);
        $this->assertDatabaseHas('riwayat_pengaturan', ['kolom' => 'peran', 'nilai_lama' => 'anggota_tim_wbs', 'nilai_baru' => null]);
        $this->assertDatabaseHas('log_audit', ['aksi' => AksiAudit::MencabutPeranKhusus->value, 'objek' => "users:{$user->id}"]);
        Mail::assertQueued(PemberitahuanPeranKhusus::class, fn (PemberitahuanPeranKhusus $surel): bool => $surel->hasTo($user->email));
        $this->actingAs($user)->get('/panel')->assertForbidden();
    }

    public function test_resetting_2fa_clears_the_secret_without_storing_it(): void
    {
        $user = User::factory()->peran(Peran::DewanPengawas)->denganMfa()->create();
        $rahasia = $user->app_authentication_secret;

        $this->artisan('sigap:peran-khusus', ['aksi' => 'atur-ulang-2fa', 'email' => $user->email, ...self::DASAR])->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->app_authentication_secret);
        $this->assertNull($user->app_authentication_recovery_codes);
        $this->assertSame(Peran::DewanPengawas, $user->peran);
        $this->assertDatabaseHas('log_audit', ['aksi' => AksiAudit::MengaturUlang2fa->value, 'objek' => "users:{$user->id}"]);
        $this->assertDatabaseHas('riwayat_pengaturan', ['kolom' => '2fa', 'nilai_baru' => '[rahasia diubah]']);
        $this->assertDatabaseMissing('riwayat_pengaturan', ['nilai_baru' => $rahasia]);
        Mail::assertQueued(PemberitahuanPeranKhusus::class, fn (PemberitahuanPeranKhusus $surel): bool => $surel->hasTo($user->email));
    }

    public function test_resetting_2fa_of_an_office_holder_managed_in_settings_is_refused(): void
    {
        $pic = User::factory()->peran(Peran::KomiteEtik)->denganMfa()->create();

        $this->artisan('sigap:peran-khusus', ['aksi' => 'atur-ulang-2fa', 'email' => $pic->email, ...self::DASAR])->assertFailed();

        $this->assertNotNull($pic->refresh()->app_authentication_secret);
    }

    public function test_the_notification_contains_no_report_content(): void
    {
        $surel = new PemberitahuanPeranKhusus(AksiAudit::MemberiPeranKhusus, Peran::KetuaTimWbs, 'Persetujuan nomor 1 tanggal 2026-11-18', 'http://localhost/panel/login');

        $surel->assertDontSeeInHtml('kode lacak');
        $surel->assertDontSeeInHtml('WBS-');
        $surel->assertSeeInHtml('Ketua Tim WBS (SPI)');
    }
}
