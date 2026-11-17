<?php

namespace Tests\Feature\Models;

use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Exceptions\PelanggaranIntegritasData;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
