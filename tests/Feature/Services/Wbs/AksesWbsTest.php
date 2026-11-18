<?php

namespace Tests\Feature\Services\Wbs;

use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Wbs\AksesWbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perutean akses WBS: matriks Bagian 3.1, lapis kedua nama terlapor (UC-02 2a, Bagian 9), dan investigator (UC-13).
 */
class AksesWbsTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    private User $anggota;

    private User $dewas;

    private User $pembina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ketua = User::factory()->peran(Peran::KetuaTimWbs)->create(['name' => 'Siti Rahmawati']);
        $this->anggota = User::factory()->peran(Peran::AnggotaTimWbs)->create(['name' => 'Budi Santoso']);
        $this->dewas = User::factory()->peran(Peran::DewanPengawas)->create(['name' => 'Hendra Wijaya']);
        $this->pembina = User::factory()->peran(Peran::Pembina)->create(['name' => 'Agus Salim']);
    }

    public function test_naming_the_ketua_routes_the_report_to_dewan_pengawas(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Bu Siti Rahmawati, S.E.']);

        $this->assertSame(KedudukanTerlapor::SpiTimWbs, AksesWbs::kedudukanEfektif($laporan));
        $this->assertFalse($this->ketua->can('bukaLaporanWbs', $laporan));
        $this->assertFalse($this->anggota->can('bukaLaporanWbs', $laporan));
        $this->assertTrue($this->dewas->can('bukaLaporanWbs', $laporan));
        $this->assertTrue($this->dewas->can('telaahLaporanWbs', $laporan));
        $this->assertSame([], Laporan::query()->wbsTerbukaBagi($this->ketua)->pluck('id')->all());
    }

    public function test_named_dewan_pengawas_member_is_treated_as_pengawas(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::Pengurus, ['hendra wijaya']);

        $this->assertSame(KedudukanTerlapor::Pengawas, AksesWbs::kedudukanEfektif($laporan));
        $this->assertFalse($this->dewas->can('bukaLaporanWbs', $laporan));
        $this->assertTrue($this->pembina->can('bukaLaporanWbs', $laporan));
        $this->assertFalse($this->ketua->can('bukaLaporanWbs', $laporan));
    }

    public function test_highest_position_among_several_reported_people_decides(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Guru tidak dikenal', 'Agus Salim']);

        $this->assertSame(KedudukanTerlapor::Pembina, AksesWbs::kedudukanEfektif($laporan));
        $this->assertFalse($this->pembina->can('bukaLaporanWbs', $laporan), 'Pembina yang disebut tidak ikut menelaah');
    }

    public function test_partial_or_unrelated_names_do_not_change_routing(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Siti', 'Budi Santosa']);

        $this->assertSame(KedudukanTerlapor::GuruPegawai, AksesWbs::kedudukanEfektif($laporan));
        $this->assertTrue($this->ketua->can('bukaLaporanWbs', $laporan));
    }

    public function test_assigned_investigator_gets_access_unless_named(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::SpiTimWbs, ['Kepala SPI']);

        $this->assertFalse($this->anggota->can('bukaLaporanWbs', $laporan));

        $laporan->update(['investigator_user_id' => $this->anggota->id]);
        $this->assertTrue($this->anggota->fresh()->can('bukaLaporanWbs', $laporan->fresh()));
        $this->assertTrue($this->anggota->can('simpulkanLaporanWbs', $laporan->fresh()));
        $this->assertFalse($this->anggota->can('telaahLaporanWbs', $laporan->fresh()));
        $this->assertContains($laporan->id, Laporan::query()->wbsTerbukaBagi($this->anggota)->pluck('id')->all());

        $laporan->terlapor()->create(['nama' => 'Budi Santoso']);
        $this->assertFalse($this->anggota->can('bukaLaporanWbs', $laporan->fresh()));
    }

    public function test_only_the_ketua_or_substitute_reviewer_may_review_and_open_identity(): void
    {
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Guru A']);

        $this->assertTrue($this->ketua->can('telaahLaporanWbs', $laporan));
        $this->assertTrue($this->ketua->can('lihatIdentitasPelapor', $laporan));
        $this->assertTrue($this->anggota->can('bukaLaporanWbs', $laporan));
        $this->assertFalse($this->anggota->can('telaahLaporanWbs', $laporan));
        $this->assertFalse($this->anggota->can('lihatIdentitasPelapor', $laporan));
    }

    public function test_kepala_sekolah_never_sees_wbs_including_reports_about_themselves(): void
    {
        $kepsek = User::factory()->peran(Peran::KepalaSekolah)->for(Unit::factory())->create(['name' => 'Rudi Hartono']);
        $tentangDirinya = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Rudi Hartono']);
        $lain = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Guru B']);

        $this->assertFalse($kepsek->can('lihatRuangKerjaWbs', Laporan::class));
        $this->assertFalse($kepsek->can('bukaLaporanWbs', $tentangDirinya));
        $this->assertFalse($kepsek->can('bukaLaporanWbs', $lain));
        $this->assertSame(0, Laporan::query()->wbsTerbukaBagi($kepsek)->count());
    }

    public function test_super_admin_has_no_access_to_any_wbs_ability(): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->create();
        $laporan = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Guru A']);
        $lampiran = Lampiran::factory()->for($laporan)->create();

        foreach (['bukaLaporanWbs', 'tanganiLaporanWbs', 'telaahLaporanWbs', 'simpulkanLaporanWbs', 'lihatIdentitasPelapor'] as $ability) {
            $this->assertFalse($superAdmin->can($ability, $laporan), $ability);
        }

        $this->assertFalse($superAdmin->can('lihatRuangKerjaWbs', Laporan::class));
        $this->assertFalse($superAdmin->can('catatLaporanMasuk', Laporan::class));
        $this->assertFalse($superAdmin->can('view', $lampiran));
    }

    public function test_attachments_and_audit_log_follow_the_same_routing(): void
    {
        $tertutup = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Siti Rahmawati']);
        $terbuka = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Guru A']);
        $lampiranTertutup = Lampiran::factory()->for($tertutup)->create();
        LogAudit::catat($this->dewas, 'membuka_laporan', $tertutup);
        LogAudit::catat($this->ketua, 'membuka_laporan', $terbuka);

        $this->assertFalse($this->ketua->can('view', $lampiranTertutup));
        $this->assertTrue($this->dewas->can('view', $lampiranTertutup));
        $this->assertSame(["laporan:{$terbuka->id}"], LogAudit::query()->terlihatOleh($this->ketua)->pluck('objek')->all());
    }

    public function test_investigator_candidates_exclude_the_named_and_the_ketua_on_closed_rows(): void
    {
        $biasa = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Guru A']);
        $spi = $this->laporan(KedudukanTerlapor::SpiTimWbs, ['Kepala SPI']);
        $menyebutAnggota = $this->laporan(KedudukanTerlapor::GuruPegawai, ['Budi Santoso']);

        $this->assertEqualsCanonicalizing([$this->ketua->id, $this->anggota->id], AksesWbs::calonInvestigator($biasa)->pluck('id')->all());
        $this->assertSame([$this->anggota->id], AksesWbs::calonInvestigator($spi)->pluck('id')->all());
        $this->assertNotContains($this->anggota->id, AksesWbs::calonInvestigator($menyebutAnggota)->pluck('id')->all());
    }

    public function test_active_reviewers_follow_the_matrix_and_skip_locked_or_named_accounts(): void
    {
        $ketuaTerkunci = User::factory()->peran(Peran::KetuaTimWbs)->terkunci()->create();

        $this->assertSame([$this->ketua->id], AksesWbs::penelaahAktif($this->laporan(KedudukanTerlapor::Pengurus, ['X']))->pluck('id')->all());
        $this->assertSame([$this->dewas->id], AksesWbs::penelaahAktif($this->laporan(KedudukanTerlapor::GuruPegawai, ['Siti Rahmawati']))->pluck('id')->all());
        $this->assertNotContains($ketuaTerkunci->id, AksesWbs::penelaahAktif($this->laporan(KedudukanTerlapor::TidakTahu, ['Y']))->pluck('id')->all());
    }

    /**
     * @param  list<string>  $terlapor
     */
    private function laporan(KedudukanTerlapor $kedudukan, array $terlapor): Laporan
    {
        $laporan = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => $kedudukan]);

        foreach ($terlapor as $nama) {
            $laporan->terlapor()->create(['nama' => $nama]);
        }

        return $laporan->fresh();
    }
}
