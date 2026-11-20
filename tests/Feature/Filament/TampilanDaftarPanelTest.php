<?php

namespace Tests\Feature\Filament;

use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Filament\Pages\Dasbor;
use App\Filament\Resources\LaporanWbs\Pages\ListLaporanWbs;
use App\Filament\Resources\Pengaduan\Pages\ListPengaduan;
use App\Filament\Resources\PengaduanUnit\Pages\ListPengaduanUnit;
use App\Filament\Widgets\PerluTindakan;
use App\Filament\Widgets\RingkasanPeran;
use App\Models\Disposisi;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tampilan daftar panel (prototipe prd/mockup/panel-*.html): tombol status, filter, pagination, pesan kosong, dan
 * tautan kartu Dasbor. Fokus utamanya: filter dan tombol status hanya mempersempit, tidak pernah membuka laporan di
 * luar hak akses (aturan proyek no. 1–3).
 */
class TampilanDaftarPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_buttons_count_and_narrow_only_the_school_admins_own_unit(): void
    {
        $unit = Unit::factory()->create();
        $adminSekolah = User::factory()->peran(Peran::AdminPengaduan)->for($unit)->denganMfa()->create();
        $diprosesMilikUnit = Laporan::factory()->for($unit)->berstatus(StatusLaporan::Diproses)->create();
        $diterimaMilikUnit = Laporan::factory()->for($unit)->create();
        $diprosesUnitLain = Laporan::factory()->count(3)->berstatus(StatusLaporan::Diproses)->create();
        Laporan::factory()->wbs()->for($unit)->create();

        $this->actingAs($adminSekolah);

        $halaman = Livewire::test(ListPengaduan::class);
        $tombol = collect($halaman->instance()->tombolStatus())->keyBy(fn (array $item): string => $item['nilai'] ?? 'semua');

        $this->assertSame(2, $tombol['semua']['jumlah']);
        $this->assertSame(1, $tombol[StatusLaporan::Diproses->value]['jumlah']);
        $this->assertSame(1, $tombol[StatusLaporan::Diterima->value]['jumlah']);
        $this->assertTrue($tombol['semua']['aktif']);

        $halaman->set('tableFilters.status.value', StatusLaporan::Diproses->value)
            ->assertCanSeeTableRecords([$diprosesMilikUnit])
            ->assertCanNotSeeTableRecords([$diterimaMilikUnit, ...$diprosesUnitLain])
            ->assertSee('Status: Diproses');
    }

    public function test_filters_never_widen_a_school_admins_scope_even_when_set_directly(): void
    {
        $unit = Unit::factory()->create();
        $unitLain = Unit::factory()->create();
        $adminSekolah = User::factory()->peran(Peran::AdminPengaduan)->for($unit)->denganMfa()->create();
        $milikUnitLain = Laporan::factory()->for($unitLain)->berstatus(StatusLaporan::Diproses)->create(['batas_waktu' => now()->subDay()]);

        $this->actingAs($adminSekolah);

        // Filter unit tersembunyi bagi Admin Sekolah; mengisinya lewat Livewire tetap tidak membuka unit lain.
        Livewire::test(ListPengaduan::class)
            ->set('tableFilters.unit_id.value', $unitLain->id)
            ->assertCanNotSeeTableRecords([$milikUnitLain])
            ->assertCountTableRecords(0)
            ->set('tableFilters.unit_id.value', null)
            ->filterTable('lewat_batas')
            ->assertCanNotSeeTableRecords([$milikUnitLain])
            ->assertCountTableRecords(0);

        $this->get('/panel/pengaduan?filters[unit_id][value]='.$unitLain->id)
            ->assertOk()
            ->assertDontSee($milikUnitLain->kode_lacak);
    }

    public function test_overdue_filter_matches_the_dashboard_card_and_its_link(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $lewatDiproses = Laporan::factory()->berstatus(StatusLaporan::Diproses)->create(['batas_waktu' => now()->subDays(2)]);
        $lewatMenunggu = Laporan::factory()->berstatus(StatusLaporan::MenungguKonfirmasi)->create(['batas_waktu' => now()->subDay()]);
        $belumLewat = Laporan::factory()->berstatus(StatusLaporan::Diproses)->create(['batas_waktu' => now()->addDays(3)]);
        $selesaiLewat = Laporan::factory()->berstatus(StatusLaporan::Selesai)->create(['batas_waktu' => now()->subDays(5)]);

        $this->actingAs($admin);

        Livewire::test(ListPengaduan::class)
            ->filterTable('lewat_batas')
            ->assertCanSeeTableRecords([$lewatDiproses, $lewatMenunggu])
            ->assertCanNotSeeTableRecords([$belumLewat, $selesaiLewat])
            ->assertSee('Lewat batas waktu');

        $tautan = RingkasanPeran::tautan($admin, 'Lewat batas waktu');
        $this->assertNotNull($tautan);

        $this->get($tautan)
            ->assertOk()
            ->assertSee($lewatDiproses->kode_lacak)
            ->assertSee($lewatMenunggu->kode_lacak)
            ->assertDontSee($belumLewat->kode_lacak)
            ->assertDontSee($selesaiLewat->kode_lacak);
    }

    public function test_pagination_shows_ten_per_page_with_range_text_and_page_size_choices(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $semua = Laporan::factory()->count(12)->sequence(fn ($urutan) => ['dibuat_pada' => now()->subMinutes($urutan->index)])->create();

        $this->actingAs($admin);

        $halaman = Livewire::test(ListPengaduan::class)
            ->assertCountTableRecords(12)
            ->assertCanSeeTableRecords($semua->take(10))
            ->assertCanNotSeeTableRecords($semua->slice(10))
            ->assertSee('Menampilkan 1–10 dari 12');

        $this->assertSame([10, 25, 50], $halaman->instance()->getTable()->getPaginationPageOptions());

        $halaman->call('gotoPage', 2, 'page')
            ->assertCanSeeTableRecords($semua->slice(10))
            ->assertCanNotSeeTableRecords($semua->take(10))
            ->assertSee('Menampilkan 11–12 dari 12');

        $halaman->set('tableRecordsPerPage', 25)
            ->assertCanSeeTableRecords($semua);
    }

    public function test_empty_result_offers_to_clear_every_filter(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create();
        $laporan = Laporan::factory()->create(['judul' => 'Keran wastafel bocor']);

        $this->actingAs($admin);

        Livewire::test(ListPengaduan::class)
            ->searchTable('tidak ada yang cocok')
            ->set('tableFilters.status.value', StatusLaporan::Ditolak->value)
            ->assertCountTableRecords(0)
            ->assertSee('Tidak ada pengaduan yang cocok dengan filter.')
            ->callAction(TestAction::make('hapusSemuaFilter')->table())
            ->assertCanSeeTableRecords([$laporan])
            ->assertSet('tableSearch', '');
    }

    public function test_wbs_filters_and_status_counts_stay_inside_the_ketua_matrix(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $unit = Unit::factory()->create();
        $terbuka = Laporan::factory()->wbs()->for($unit)->create(['kedudukan_terlapor' => KedudukanTerlapor::GuruPegawai, 'sumber' => SumberLaporan::Surel]);
        $lain = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::Pengurus, 'sumber' => SumberLaporan::Laman]);
        $tentangSpi = Laporan::factory()->wbs()->for($unit)->create(['kedudukan_terlapor' => KedudukanTerlapor::SpiTimWbs, 'sumber' => SumberLaporan::Surel]);
        $tentangPembina = Laporan::factory()->wbs()->for($unit)->berstatus(StatusLaporan::Ditelaah)->create(['kedudukan_terlapor' => KedudukanTerlapor::Pembina]);

        $this->actingAs($ketua);

        $halaman = Livewire::test(ListLaporanWbs::class);
        $tombol = collect($halaman->instance()->tombolStatus())->keyBy(fn (array $item): string => $item['nilai'] ?? 'semua');

        $this->assertSame(2, $tombol['semua']['jumlah']);
        $this->assertSame(0, $tombol[StatusLaporan::Ditelaah->value]['jumlah']);

        $halaman
            ->filterTable('unit_id', $unit->id)
            ->assertCanSeeTableRecords([$terbuka])
            ->assertCanNotSeeTableRecords([$lain, $tentangSpi, $tentangPembina])
            ->resetTableFilters()
            ->filterTable('kedudukan_terlapor', KedudukanTerlapor::SpiTimWbs)
            ->assertCountTableRecords(0)
            ->resetTableFilters()
            ->filterTable('sumber', SumberLaporan::Surel)
            ->assertCanSeeTableRecords([$terbuka])
            ->assertCanNotSeeTableRecords([$lain, $tentangSpi])
            ->resetTableFilters()
            ->set('tableFilters.status.value', StatusLaporan::Ditelaah->value)
            ->assertCountTableRecords(0)
            ->assertSee('Tidak ada laporan yang cocok dengan filter.');
    }

    public function test_wbs_search_finds_only_by_tracking_code_within_the_matrix(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $terbuka = Laporan::factory()->wbs()->create(['kode_lacak' => 'WBS-AB12-CD34']);
        $tentangSpi = Laporan::factory()->wbs()->create(['kode_lacak' => 'WBS-AB12-ZZ99', 'kedudukan_terlapor' => KedudukanTerlapor::SpiTimWbs]);

        $this->actingAs($ketua);

        Livewire::test(ListLaporanWbs::class)
            ->searchTable('AB12')
            ->assertCanSeeTableRecords([$terbuka])
            ->assertCanNotSeeTableRecords([$tentangSpi]);
    }

    public function test_pic_card_filters_match_the_unit_scope(): void
    {
        $unit = Unit::factory()->create();
        $pic = User::factory()->peran(Peran::PicUnit)->for($unit)->denganMfa()->create();
        $belumDitindaklanjuti = Laporan::factory()->for($unit)->berstatus(StatusLaporan::Diproses)->create();
        Disposisi::factory()->for($belumDitindaklanjuti)->for($unit)->create();
        $sudahDitindaklanjuti = Laporan::factory()->for($unit)->berstatus(StatusLaporan::Diproses)->create();
        Disposisi::factory()->for($sudahDitindaklanjuti)->for($unit)->create(['tindak_lanjut' => 'Sudah diperbaiki']);
        $unitLain = Laporan::factory()->berstatus(StatusLaporan::Diproses)->create();
        Disposisi::factory()->for($unitLain)->create();

        $this->actingAs($pic);

        Livewire::test(ListPengaduanUnit::class)
            ->filterTable('belum_ditindaklanjuti')
            ->assertCanSeeTableRecords([$belumDitindaklanjuti])
            ->assertCanNotSeeTableRecords([$sudahDitindaklanjuti, $unitLain]);
    }

    public function test_needs_action_list_respects_each_roles_scope(): void
    {
        $unit = Unit::factory()->create();
        $milikUnit = Laporan::factory()->for($unit)->create(['judul' => 'Lampu kelas mati']);
        $unitLain = Laporan::factory()->create(['judul' => 'AC ruang guru bocor']);
        $wbsTentangSpi = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::SpiTimWbs]);
        $wbsTerbuka = Laporan::factory()->wbs()->create();

        $this->actingAs(User::factory()->peran(Peran::AdminPengaduan)->for($unit)->denganMfa()->create());
        Livewire::test(PerluTindakan::class)->assertSee('Lampu kelas mati')->assertDontSee('AC ruang guru bocor')->assertDontSee('WBS-');

        $this->actingAs(User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create());
        Livewire::test(PerluTindakan::class)
            ->assertSee($wbsTerbuka->kode_lacak)
            ->assertDontSee($wbsTentangSpi->kode_lacak)
            ->assertDontSee($milikUnit->kode_lacak);

        $this->actingAs(User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create());
        $this->assertNotContains('Laporan WBS yang sedang berjalan', array_column(PerluTindakan::bagian(), 'judul'));
        Livewire::test(PerluTindakan::class)->assertDontSee($wbsTerbuka->kode_lacak)->assertDontSee($milikUnit->kode_lacak);
    }

    public function test_dashboard_has_no_welcome_card_and_no_external_avatar(): void
    {
        $this->actingAs(User::factory()->peran(Peran::AdminPengaduan)->denganMfa()->create(['name' => 'Uji Admin']));

        $this->get('/panel')
            ->assertOk()
            ->assertDontSee('ui-avatars.com');

        $widget = (new Dasbor)->getWidgets();
        $this->assertNotContains(AccountWidget::class, $widget);
        $this->assertContains(PerluTindakan::class, $widget);
        $this->assertContains(RingkasanPeran::class, $widget);
    }
}
