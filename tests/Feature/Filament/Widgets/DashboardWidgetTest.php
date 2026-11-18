<?php

namespace Tests\Feature\Filament\Widgets;

use App\Enums\Peran;
use App\Filament\Widgets\PengaduanPerBulan;
use App\Filament\Widgets\RingkasanPeran;
use App\Filament\Widgets\WbsPerTriwulan;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * H-10 Dashboard (US-22): widget tampil sesuai peran.
 */
class DashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{Peran, bool, bool}>
     */
    public static function peran(): array
    {
        return [
            'super admin' => [Peran::SuperAdmin, false, false],
            'admin pengaduan' => [Peran::AdminPengaduan, true, false],
            'pic unit' => [Peran::PicUnit, true, false],
            'ketua tim wbs' => [Peran::KetuaTimWbs, false, false],
            'kepala sekolah' => [Peran::KepalaSekolah, false, false],
            'ketua umum' => [Peran::KetuaUmum, true, true],
            'pengurus harian' => [Peran::PengurusHarian, true, true],
            'dewan pengawas' => [Peran::DewanPengawas, true, true],
        ];
    }

    #[DataProvider('peran')]
    public function test_dashboard_widgets_follow_the_role(Peran $peran, bool $grafikPengaduan, bool $jumlahWbs): void
    {
        $user = User::factory()->peran($peran)->denganMfa()->create(['unit_id' => $peran->wajibUnit() ? Unit::factory()->create()->id : null]);
        $this->actingAs($user);

        $this->get('/panel')->assertOk();
        $this->assertTrue(RingkasanPeran::canView());
        $this->assertSame($grafikPengaduan, PengaduanPerBulan::canView());
        $this->assertSame($jumlahWbs, WbsPerTriwulan::canView());
    }

    public function test_leadership_sees_wbs_counts_only_from_three_without_any_report_detail(): void
    {
        $kategori = Kategori::factory()->wbs()->create(['nama' => 'Penyalahgunaan dana']);
        Laporan::factory()->wbs()->count(3)->for($kategori)->create(['judul' => 'Dana BOS dipakai pribadi']);
        Kategori::factory()->wbs()->create(['nama' => 'Kecurangan nilai']);
        $this->actingAs(User::factory()->peran(Peran::KetuaUmum)->denganMfa()->create());

        Livewire::test(WbsPerTriwulan::class)
            ->assertSee('Penyalahgunaan dana')
            ->assertSee('Kecurangan nilai')
            ->assertSee('&lt; 3', false)
            ->assertDontSee('Dana BOS dipakai pribadi');
    }

    public function test_complaint_chart_for_a_school_admin_has_only_their_school(): void
    {
        $sd = Unit::factory()->create(['nama' => 'SD YPVDP']);
        Laporan::factory()->for($sd)->create();
        Laporan::factory()->for(Unit::factory()->create(['nama' => 'SMA YPVDP']))->create();
        $this->actingAs(User::factory()->peran(Peran::AdminPengaduan)->for($sd)->denganMfa()->create());

        Livewire::test(PengaduanPerBulan::class)->assertSee('SD YPVDP')->assertDontSee('SMA YPVDP');
    }
}
