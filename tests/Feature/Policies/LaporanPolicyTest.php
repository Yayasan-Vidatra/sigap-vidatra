<?php

namespace Tests\Feature\Policies;

use App\Enums\Peran;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LaporanPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranSelainAdminPengaduan(): array
    {
        return collect(Peran::cases())
            ->reject(fn (Peran $peran): bool => $peran === Peran::AdminPengaduan)
            ->mapWithKeys(fn (Peran $peran): array => [$peran->value => [$peran]])
            ->all();
    }

    public function test_admin_pengaduan_opens_the_complaint_inbox(): void
    {
        $yayasan = User::factory()->peran(Peran::AdminPengaduan)->create();
        $sekolah = User::factory()->peran(Peran::AdminPengaduan)->for(Unit::factory())->create();

        $this->assertTrue($yayasan->can('lihatKotakMasukPengaduan', Laporan::class));
        $this->assertTrue($sekolah->can('lihatKotakMasukPengaduan', Laporan::class));
    }

    #[DataProvider('peranSelainAdminPengaduan')]
    public function test_other_peran_cannot_open_the_inbox_or_handle_any_complaint(Peran $peran): void
    {
        $laporan = Laporan::factory()->create();
        $user = User::factory()->peran($peran)->create(['unit_id' => $peran->bolehUnit() ? $laporan->unit_id : null]);

        $this->assertFalse($user->can('lihatKotakMasukPengaduan', Laporan::class));
        $this->assertFalse($user->can('kelolaPengaduan', $laporan));
        $this->assertFalse($user->can('lihatIdentitasPelapor', $laporan));
    }

    public function test_foundation_admin_handles_complaints_of_every_unit(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();

        $this->assertTrue($admin->can('kelolaPengaduan', Laporan::factory()->create()));
        $this->assertTrue($admin->can('kelolaPengaduan', Laporan::factory()->create()));
    }

    public function test_school_admin_handles_only_complaints_of_their_own_unit(): void
    {
        $unit = Unit::factory()->create();
        $adminSekolah = User::factory()->peran(Peran::AdminPengaduan)->for($unit)->create();

        $this->assertTrue($adminSekolah->can('kelolaPengaduan', Laporan::factory()->for($unit)->create()));
        $this->assertTrue($adminSekolah->can('lihatIdentitasPelapor', Laporan::factory()->for($unit)->create()));
        $this->assertFalse($adminSekolah->can('kelolaPengaduan', Laporan::factory()->create()));
        $this->assertFalse($adminSekolah->can('lihatIdentitasPelapor', Laporan::factory()->create()));
    }

    public function test_no_complaint_admin_can_handle_a_wbs_report(): void
    {
        $unit = Unit::factory()->create();
        $wbs = Laporan::factory()->wbs()->for($unit)->create();

        $this->assertFalse(User::factory()->peran(Peran::AdminPengaduan)->create()->can('kelolaPengaduan', $wbs));
        $this->assertFalse(User::factory()->peran(Peran::AdminPengaduan)->for($unit)->create()->can('kelolaPengaduan', $wbs));
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function semuaPeran(): array
    {
        return collect(Peran::cases())->mapWithKeys(fn (Peran $peran): array => [$peran->value => [$peran]])->all();
    }

    #[DataProvider('semuaPeran')]
    public function test_default_filament_abilities_stay_closed_so_new_report_pages_do_not_open_by_accident(Peran $peran): void
    {
        $user = User::factory()->peran($peran)->create();
        $laporan = Laporan::factory()->create();

        $this->assertFalse($user->can('viewAny', Laporan::class));
        $this->assertFalse($user->can('view', $laporan));
        $this->assertFalse($user->can('create', Laporan::class));
        $this->assertFalse($user->can('update', $laporan));
        $this->assertFalse($user->can('delete', $laporan));
        $this->assertFalse($user->can('deleteAny', Laporan::class));
    }
}
