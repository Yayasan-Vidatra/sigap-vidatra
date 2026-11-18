<?php

namespace Tests\Feature\Filament\Resources\Pengaduan\Pages;

use App\Enums\Peran;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Filament\Resources\Pengaduan\Pages\ListPengaduan;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListPengaduanTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_admin_sees_complaints_of_every_unit_but_never_wbs_reports(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $pengaduan = Laporan::factory()->count(2)->create();
        $wbs = Laporan::factory()->wbs()->create(['unit_id' => $pengaduan[0]->unit_id]);

        $this->actingAs($admin);

        Livewire::test(ListPengaduan::class)
            ->assertCanSeeTableRecords($pengaduan)
            ->assertCanNotSeeTableRecords([$wbs])
            ->assertCountTableRecords(2);
    }

    public function test_school_admin_sees_only_complaints_of_their_unit(): void
    {
        $unit = Unit::factory()->create();
        $adminSekolah = User::factory()->peran(Peran::AdminPengaduan)->for($unit)->denganMfa()->create();
        $milikUnit = Laporan::factory()->for($unit)->create();
        $unitLain = Laporan::factory()->create();
        $wbsUnit = Laporan::factory()->wbs()->for($unit)->create();

        $this->actingAs($adminSekolah);

        Livewire::test(ListPengaduan::class)
            ->assertCanSeeTableRecords([$milikUnit])
            ->assertCanNotSeeTableRecords([$unitLain, $wbsUnit])
            ->assertTableFilterHidden('unit_id');
    }

    public function test_the_inbox_page_opens_for_admin_pengaduan(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $laporan = Laporan::factory()->create(['judul' => 'Toilet lantai dua rusak']);

        $response = $this->actingAs($admin)->get('/panel/pengaduan');

        $response->assertOk();
        $response->assertSee($laporan->kode_lacak);
        $response->assertSee('Toilet lantai dua rusak');
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranTanpaAkses(): array
    {
        return [
            'super admin' => [Peran::SuperAdmin],
            'pic unit' => [Peran::PicUnit],
            'ketua tim wbs' => [Peran::KetuaTimWbs],
            'kepala sekolah' => [Peran::KepalaSekolah],
            'ketua umum' => [Peran::KetuaUmum],
        ];
    }

    #[DataProvider('peranTanpaAkses')]
    public function test_other_roles_are_forbidden_from_the_inbox(Peran $peran): void
    {
        $user = User::factory()->peran($peran)->denganMfa()->create();

        $response = $this->actingAs($user)->get('/panel/pengaduan');

        $response->assertForbidden();
    }

    public function test_search_finds_a_complaint_by_tracking_code_or_title(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $dicari = Laporan::factory()->create(['kode_lacak' => 'PGD-2611-ABC234', 'judul' => 'AC kelas 7B mati']);
        $lain = Laporan::factory()->create(['kode_lacak' => 'PGD-2611-XYZ789', 'judul' => 'Jadwal ekstrakurikuler bentrok']);

        $this->actingAs($admin);

        Livewire::test(ListPengaduan::class)
            ->searchTable('ABC234')
            ->assertCanSeeTableRecords([$dicari])
            ->assertCanNotSeeTableRecords([$lain])
            ->searchTable('kelas 7B')
            ->assertCanSeeTableRecords([$dicari])
            ->assertCanNotSeeTableRecords([$lain]);
    }

    public function test_date_filter_and_times_follow_wita(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        // Rabu 18 Nov 2026 07.46 WITA = Selasa 17 Nov 23.46 UTC.
        $rabuPagi = Laporan::factory()->create(['dibuat_pada' => CarbonImmutable::parse('2026-11-17 23:46:00', 'UTC')]);
        $selasaSore = Laporan::factory()->create(['dibuat_pada' => CarbonImmutable::parse('2026-11-17 10:00:00', 'UTC')]);

        $this->actingAs($admin);

        Livewire::test(ListPengaduan::class)
            ->assertSee('18 Nov 2026 07:46 WITA')
            ->filterTable('dibuat_pada', ['dari' => '2026-11-18', 'sampai' => '2026-11-18'])
            ->assertCanSeeTableRecords([$rabuPagi])
            ->assertCanNotSeeTableRecords([$selasaSore]);
    }

    public function test_filters_narrow_by_unit_category_status_priority_and_date(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $unit = Unit::factory()->create();
        $kategori = Kategori::factory()->create();
        $cocok = Laporan::factory()->for($unit)->for($kategori)->berstatus(StatusLaporan::Diproses)->create([
            'prioritas' => Prioritas::Tinggi,
            'dibuat_pada' => CarbonImmutable::parse('2026-11-10 08:00:00'),
        ]);
        $lain = Laporan::factory()->create([
            'dibuat_pada' => CarbonImmutable::parse('2026-11-01 08:00:00'),
        ]);

        $this->actingAs($admin);

        $halaman = Livewire::test(ListPengaduan::class);

        $halaman->filterTable('unit_id', $unit->id)->assertCanSeeTableRecords([$cocok])->assertCanNotSeeTableRecords([$lain])->resetTableFilters();
        $halaman->filterTable('kategori_id', $kategori->id)->assertCanSeeTableRecords([$cocok])->assertCanNotSeeTableRecords([$lain])->resetTableFilters();
        $halaman->filterTable('status', StatusLaporan::Diproses->value)->assertCanSeeTableRecords([$cocok])->assertCanNotSeeTableRecords([$lain])->resetTableFilters();
        $halaman->filterTable('prioritas', Prioritas::Tinggi->value)->assertCanSeeTableRecords([$cocok])->assertCanNotSeeTableRecords([$lain])->resetTableFilters();
        $halaman->filterTable('dibuat_pada', ['dari' => '2026-11-05', 'sampai' => '2026-11-15'])
            ->assertCanSeeTableRecords([$cocok])
            ->assertCanNotSeeTableRecords([$lain]);
    }
}
