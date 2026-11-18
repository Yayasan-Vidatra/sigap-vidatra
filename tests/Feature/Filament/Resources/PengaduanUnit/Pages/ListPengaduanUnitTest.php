<?php

namespace Tests\Feature\Filament\Resources\PengaduanUnit\Pages;

use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Filament\Resources\PengaduanUnit\Pages\ListPengaduanUnit;
use App\Models\Disposisi;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListPengaduanUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_pic_sees_only_complaints_disposed_to_their_unit(): void
    {
        $unit = Unit::factory()->create();
        $pic = User::factory()->peran(Peran::PicUnit)->for($unit)->denganMfa()->create();
        $milikUnit = Laporan::factory()->for($unit)->berstatus(StatusLaporan::Diproses)->create();
        Disposisi::factory()->for($milikUnit)->for($unit)->create();
        $belumDisposisi = Laporan::factory()->for($unit)->create();
        $unitLain = Laporan::factory()->berstatus(StatusLaporan::Diproses)->create();
        Disposisi::factory()->for($unitLain)->create(['unit_id' => $unitLain->unit_id]);
        $wbs = Laporan::factory()->wbs()->for($unit)->create();

        $this->actingAs($pic);

        Livewire::test(ListPengaduanUnit::class)
            ->assertCanSeeTableRecords([$milikUnit])
            ->assertCanNotSeeTableRecords([$belumDisposisi, $unitLain, $wbs]);
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranTanpaAkses(): array
    {
        return [
            'admin pengaduan' => [Peran::AdminPengaduan],
            'super admin' => [Peran::SuperAdmin],
            'kepala sekolah' => [Peran::KepalaSekolah],
            'ketua tim wbs' => [Peran::KetuaTimWbs],
        ];
    }

    #[DataProvider('peranTanpaAkses')]
    public function test_other_roles_are_forbidden(Peran $peran): void
    {
        $user = User::factory()->peran($peran)->denganMfa()->create();

        $this->actingAs($user)->get('/panel/pengaduan-unit')->assertForbidden();
    }

    public function test_pic_does_not_see_the_admin_inbox(): void
    {
        $pic = User::factory()->peran(Peran::PicUnit)->denganMfa()->create();

        $this->actingAs($pic)->get('/panel/pengaduan')->assertForbidden();
        $this->actingAs($pic)->get('/panel/pengaduan-unit')->assertOk();
    }
}
