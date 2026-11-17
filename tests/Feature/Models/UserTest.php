<?php

namespace Tests\Feature\Models;

use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Exceptions\PelanggaranIntegritasData;
use App\Models\Unit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_pic_unit_belongs_to_its_unit(): void
    {
        $unit = Unit::factory()->create();

        $pic = User::factory()->peran(Peran::PicUnit)->for($unit)->create();

        $this->assertTrue($pic->fresh()->unit->is($unit));
        $this->assertSame([$pic->id], $unit->users->modelKeys());
        $this->assertSame(Peran::PicUnit, $pic->fresh()->peran);
    }

    public function test_new_user_has_no_peran_and_a_local_account(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->fresh()->peran);
        $this->assertSame(JenisAkun::Lokal, $user->fresh()->jenis_akun);
    }

    public function test_admin_pengaduan_with_a_unit_is_admin_sekolah(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->for(Unit::factory())->create();

        $this->assertTrue($admin->isAdminSekolah());
        $this->assertFalse($admin->isAdminYayasan());
    }

    public function test_admin_pengaduan_without_a_unit_covers_the_whole_yayasan(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();

        $this->assertTrue($admin->isAdminYayasan());
        $this->assertFalse($admin->isAdminSekolah());
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranWajibUnit(): array
    {
        return [
            'pic unit' => [Peran::PicUnit],
            'kepala sekolah' => [Peran::KepalaSekolah],
        ];
    }

    #[DataProvider('peranWajibUnit')]
    public function test_peran_bound_to_a_unit_without_unit_is_rejected(Peran $peran): void
    {
        $this->expectException(PelanggaranIntegritasData::class);

        try {
            User::factory()->create(['peran' => $peran, 'unit_id' => null]);
        } finally {
            $this->assertDatabaseCount('users', 0);
        }
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranTanpaUnit(): array
    {
        return [
            'ketua tim wbs' => [Peran::KetuaTimWbs],
            'super admin' => [Peran::SuperAdmin],
            'dewan pengawas' => [Peran::DewanPengawas],
        ];
    }

    #[DataProvider('peranTanpaUnit')]
    public function test_unit_on_a_yayasan_level_peran_is_rejected(Peran $peran): void
    {
        $unit = Unit::factory()->create();

        $this->expectException(PelanggaranIntegritasData::class);

        try {
            User::factory()->create(['peran' => $peran, 'unit_id' => $unit->id]);
        } finally {
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_authenticator_secret_is_stored_encrypted_and_hidden(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->create(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);

        $mentah = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');

        $this->assertSame('JBSWY3DPEHPK3PXP', Crypt::decryptString($mentah));
        $this->assertArrayNotHasKey('app_authentication_secret', $user->toArray());
    }

    public function test_fourth_failed_login_keeps_the_account_open(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->create(['gagal_login' => 3]);

        $baruTerkunci = $user->catatGagalLogin();

        $this->assertFalse($baruTerkunci);
        $this->assertSame(4, $user->fresh()->gagal_login);
        $this->assertFalse($user->fresh()->terkunci());
    }

    public function test_fifth_failed_login_locks_the_account_and_closes_the_panel(): void
    {
        $this->freezeTime();
        $user = User::factory()->peran(Peran::KetuaTimWbs)->create(['gagal_login' => 4]);

        $baruTerkunci = $user->catatGagalLogin();

        $this->assertTrue($baruTerkunci);
        $this->assertSame(now()->toDateTimeString(), $user->fresh()->terkunci_pada->toDateTimeString());
        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getPanel('panel')));
    }

    public function test_unlocking_resets_the_failure_count(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->terkunci()->create();

        $user->bukaKunci();

        $this->assertSame(0, $user->fresh()->gagal_login);
        $this->assertFalse($user->fresh()->terkunci());
        $this->assertTrue($user->fresh()->canAccessPanel(Filament::getPanel('panel')));
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranAkunLokal(): array
    {
        return [
            'ketua tim wbs' => [Peran::KetuaTimWbs],
            'dewan pengawas' => [Peran::DewanPengawas],
            'komite etik' => [Peran::KomiteEtik],
            'super admin' => [Peran::SuperAdmin],
        ];
    }

    #[DataProvider('peranAkunLokal')]
    public function test_sso_account_for_a_local_only_peran_is_rejected(Peran $peran): void
    {
        $this->expectException(PelanggaranIntegritasData::class);

        try {
            User::factory()->sso()->create(['peran' => $peran]);
        } finally {
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_admin_pengaduan_and_pic_unit_may_have_sso_accounts(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->sso()->create();
        $pic = User::factory()->peran(Peran::PicUnit)->sso()->create();

        $this->assertSame(JenisAkun::Sso, $admin->fresh()->jenis_akun);
        $this->assertSame(JenisAkun::Sso, $pic->fresh()->jenis_akun);
    }
}
