<?php

namespace Tests\Feature\Policies;

use App\Enums\AksiAudit;
use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LogAuditPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{Peran}>
     */
    public static function semuaPeran(): array
    {
        return collect(Peran::cases())->mapWithKeys(fn (Peran $peran): array => [$peran->value => [$peran]])->all();
    }

    #[DataProvider('semuaPeran')]
    public function test_nobody_may_create_update_or_delete_audit_logs(Peran $peran): void
    {
        $user = User::factory()->peran($peran)->create();
        $log = LogAudit::factory()->create();

        $this->assertFalse($user->can('create', LogAudit::class));
        $this->assertFalse($user->can('update', $log));
        $this->assertFalse($user->can('delete', $log));
        $this->assertFalse($user->can('deleteAny', LogAudit::class));
        $this->assertFalse($user->can('forceDelete', $log));
    }

    public function test_ketua_tim_wbs_cannot_view_the_log_of_a_report_about_spi(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->create();
        $tentangSpi = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::SpiTimWbs]);
        $terbuka = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::TidakTahu]);

        $this->assertFalse($ketua->can('view', LogAudit::catat(null, AksiAudit::MembukaLaporan, $tentangSpi)));
        $this->assertTrue($ketua->can('view', LogAudit::catat(null, AksiAudit::MembukaLaporan, $terbuka)));
    }
}
