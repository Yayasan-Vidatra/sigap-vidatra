<?php

namespace Tests\Feature\Filament\Resources\LogAudits\Pages;

use App\Enums\AksiAudit;
use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Filament\Resources\LogAudits\Pages\ListLogAudits;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListLogAuditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_reads_every_log_row(): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();
        $log = $this->buatLog();

        $this->actingAs($superAdmin);

        Livewire::test(ListLogAudits::class)
            ->assertCanSeeTableRecords(array_values($log));
    }

    public function test_super_admin_page_shows_no_wbs_content(): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();
        $wbs = Laporan::factory()->wbs()->create(['judul' => 'Dana BOS dipakai pribadi']);
        LogAudit::catat(null, AksiAudit::MembukaLaporan, $wbs);

        $response = $this->actingAs($superAdmin)->get('/panel/log-audit');

        $response->assertOk();
        $response->assertSee("laporan:{$wbs->id}");
        $response->assertDontSee('Dana BOS dipakai pribadi');
    }

    public function test_ketua_tim_wbs_reads_only_logs_of_wbs_reports_open_to_them(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->denganMfa()->create();
        $log = $this->buatLog();

        $this->actingAs($ketua);

        Livewire::test(ListLogAudits::class)
            ->assertCanSeeTableRecords([$log['wbsTerbuka']])
            ->assertCanNotSeeTableRecords([$log['wbsTentangSpi'], $log['pengaduan'], $log['login']]);
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranTanpaAkses(): array
    {
        return [
            'admin pengaduan' => [Peran::AdminPengaduan],
            'pic unit' => [Peran::PicUnit],
            'anggota tim wbs' => [Peran::AnggotaTimWbs],
            'dewan pengawas' => [Peran::DewanPengawas],
            'ketua umum' => [Peran::KetuaUmum],
        ];
    }

    #[DataProvider('peranTanpaAkses')]
    public function test_other_peran_are_forbidden_from_the_audit_log(Peran $peran): void
    {
        $user = User::factory()->peran($peran)->denganMfa()->create();

        $response = $this->actingAs($user)->get('/panel/log-audit');

        $response->assertForbidden();
    }

    public function test_audit_log_has_no_create_edit_or_delete(): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();
        $log = LogAudit::factory()->create();

        $this->actingAs($superAdmin);

        $this->assertFalse(Route::has('filament.panel.resources.log-audit.create'));
        $this->assertFalse(Route::has('filament.panel.resources.log-audit.edit'));
        Livewire::test(ListLogAudits::class)
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertTableBulkActionDoesNotExist('delete');
        $this->assertModelExists($log);
    }

    /**
     * @return array{pengaduan: LogAudit, wbsTerbuka: LogAudit, wbsTentangSpi: LogAudit, login: LogAudit}
     */
    private function buatLog(): array
    {
        $petugas = User::factory()->peran(Peran::AnggotaTimWbs)->create();

        return [
            'pengaduan' => LogAudit::catat($petugas, AksiAudit::MembukaLaporan, Laporan::factory()->create()),
            'wbsTerbuka' => LogAudit::catat($petugas, AksiAudit::MembukaLaporan, Laporan::factory()->wbs()->create()),
            'wbsTentangSpi' => LogAudit::catat($petugas, AksiAudit::MembukaLaporan, Laporan::factory()->wbs()->create([
                'kedudukan_terlapor' => KedudukanTerlapor::SpiTimWbs,
            ])),
            'login' => LogAudit::catat($petugas, AksiAudit::LoginBerhasil, $petugas),
        ];
    }
}
