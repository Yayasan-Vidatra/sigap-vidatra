<?php

namespace Tests\Feature\Database\Seeders;

use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Models\User;
use Database\Seeders\AkunUjiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class AkunUjiSeederTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['testing'])]
    #[TestWith(['production'])]
    #[TestWith(['staging'])]
    public function test_creates_nothing_outside_the_local_environment(string $environment): void
    {
        $this->app->detectEnvironment(fn (): string => $environment);
        config(['sigap.akun_uji.password' => 'kata-sandi-uji']);

        // --force melewati konfirmasi db:seed di production, agar yang diuji adalah penjagaan seeder sendiri.
        $this->artisan('db:seed', ['--class' => AkunUjiSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('units', 0);
    }

    #[TestWith([null])]
    #[TestWith([''])]
    public function test_stops_with_a_clear_message_when_the_password_is_empty(?string $kataSandi): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config(['sigap.akun_uji.password' => $kataSandi]);

        try {
            $this->seed(AkunUjiSeeder::class);
            $this->fail('Seeder seharusnya berhenti bila AKUN_UJI_PASSWORD kosong.');
        } catch (RuntimeException $galat) {
            $this->assertStringContainsString('AKUN_UJI_PASSWORD kosong', $galat->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_creates_one_local_account_per_role_with_the_right_unit(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config(['sigap.akun_uji.password' => 'kata-sandi-uji']);

        $this->seed(AkunUjiSeeder::class);

        $akun = User::query()->with('unit')->get()->keyBy('email');
        $this->assertCount(12, $akun);
        $this->assertTrue($akun->every(fn (User $user): bool => $user->jenis_akun === JenisAkun::Lokal));
        $this->assertTrue(Hash::check('kata-sandi-uji', $akun['admin@sigap.test']->password));

        $this->assertSame(Peran::SuperAdmin, $akun['superadmin@sigap.test']->peran);
        $this->assertTrue($akun['admin@sigap.test']->isAdminYayasan());
        $this->assertTrue($akun['admin.sd@sigap.test']->isAdminSekolah());
        $this->assertSame('SD YPVDP', $akun['admin.sd@sigap.test']->unit->nama);
        $this->assertSame(
            ['pic.ph@sigap.test' => 'Pelaksana Harian', 'pic.sd@sigap.test' => 'SD YPVDP', 'pic.sma@sigap.test' => 'SMA YPVDP', 'pic.smp@sigap.test' => 'SMP YPVDP'],
            $akun->filter(fn (User $user): bool => $user->peran === Peran::PicUnit)->map(fn (User $user): string => $user->unit->nama)->sortKeys()->all(),
        );
        $this->assertSame(Peran::KetuaTimWbs, $akun['ketua.wbs@sigap.test']->peran);
        $this->assertSame(Peran::AnggotaTimWbs, $akun['anggota.wbs@sigap.test']->peran);
        $this->assertSame(Peran::DewanPengawas, $akun['dewas@sigap.test']->peran);
        $this->assertSame(Peran::KepalaSekolah, $akun['kepsek.sd@sigap.test']->peran);
        $this->assertSame('SD YPVDP', $akun['kepsek.sd@sigap.test']->unit->nama);
        $this->assertSame(Peran::KomiteEtik, $akun['etik@sigap.test']->peran);
        $this->assertNull($akun['etik@sigap.test']->unit_id);
    }

    public function test_running_twice_updates_the_same_accounts_without_duplicates(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config(['sigap.akun_uji.password' => 'kata-sandi-lama']);
        $this->seed(AkunUjiSeeder::class);
        $idPertama = User::query()->orderBy('email')->pluck('id', 'email')->all();

        config(['sigap.akun_uji.password' => 'kata-sandi-baru']);
        $this->seed(AkunUjiSeeder::class);

        $this->assertDatabaseCount('users', 12);
        $this->assertDatabaseCount('units', 5);
        $this->assertSame($idPertama, User::query()->orderBy('email')->pluck('id', 'email')->all());
        $this->assertTrue(Hash::check('kata-sandi-baru', User::query()->where('email', 'pic.sd@sigap.test')->value('password')));
    }
}
