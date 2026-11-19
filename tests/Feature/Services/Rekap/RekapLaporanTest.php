<?php

namespace Tests\Feature\Services\Rekap;

use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\TujuanPengalihan;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Pengalihan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Rekap\RekapLaporan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Angka dashboard H-10 (US-22) dan rekap H-18 (US-23) mengikuti scope hak akses.
 */
class RekapLaporanTest extends TestCase
{
    use RefreshDatabase;

    private Unit $sd;

    private Unit $smp;

    private RekapLaporan $rekap;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-19 10:00:00'));
        $this->sd = Unit::factory()->create(['nama' => 'SD YPVDP']);
        $this->smp = Unit::factory()->create(['nama' => 'SMP YPVDP']);
        $this->rekap = app(RekapLaporan::class);
    }

    public function test_school_admin_counts_only_their_school_and_foundation_admin_counts_all(): void
    {
        Laporan::factory()->count(2)->for($this->sd)->create();
        Laporan::factory()->count(3)->for($this->smp)->create();
        Laporan::factory()->wbs()->for($this->sd)->create();

        $adminSd = User::factory()->peran(Peran::AdminPengaduan)->for($this->sd)->create();
        $adminYayasan = User::factory()->peran(Peran::AdminPengaduan)->create();

        $this->assertSame(2, $this->kartu($adminSd, 'Pengaduan baru'));
        $this->assertSame(5, $this->kartu($adminYayasan, 'Pengaduan baru'));
        $this->assertSame(['SD YPVDP'], array_keys($this->rekap->pengaduanPerUnitPerBulan($adminSd, 2026)));
        $this->assertSame(3, array_sum($this->rekap->pengaduanPerUnitPerBulan($adminYayasan, 2026)['SMP YPVDP']));
    }

    public function test_pic_counts_only_complaints_dispositioned_to_their_unit(): void
    {
        $pic = User::factory()->peran(Peran::PicUnit)->for($this->sd)->create();
        $didisposisi = Laporan::factory()->berstatus(StatusLaporan::Diproses)->for($this->sd)->create(['batas_waktu' => now()->subDay()]);
        $didisposisi->disposisi()->create(['unit_id' => $this->sd->id, 'pic_user_id' => $pic->id]);
        Laporan::factory()->berstatus(StatusLaporan::Diproses)->for($this->sd)->create(['batas_waktu' => now()->subDay()]);
        $lain = Laporan::factory()->berstatus(StatusLaporan::Diproses)->for($this->smp)->create();
        $lain->disposisi()->create(['unit_id' => $this->smp->id, 'pic_user_id' => User::factory()->peran(Peran::PicUnit)->for($this->smp)->create()->id]);

        $this->assertSame(1, $this->kartu($pic, 'Menunggu tindakan Anda'));
        $this->assertSame(1, $this->kartu($pic, 'Lewat batas waktu'));
        $this->assertSame([$didisposisi->id], Laporan::query()->rekapPengaduanBagi($pic)->pluck('id')->all());
        $this->assertSame(['SD YPVDP'], array_keys($this->rekap->pengaduanPerUnitPerBulan($pic, 2026)));
    }

    public function test_wbs_counts_per_category_per_quarter_are_hidden_below_three_and_have_no_totals(): void
    {
        $dana = Kategori::factory()->wbs()->create(['nama' => 'Penyalahgunaan dana']);
        $nilai = Kategori::factory()->wbs()->create(['nama' => 'Kecurangan nilai']);
        Laporan::factory()->wbs()->count(3)->for($dana)->create(['dibuat_pada' => '2026-02-10 02:00:00']);
        Laporan::factory()->wbs()->count(2)->for($dana)->create(['dibuat_pada' => '2026-05-10 02:00:00']);
        Laporan::factory()->wbs()->count(4)->for($nilai)->create(['dibuat_pada' => '2026-09-30 17:00:00']);
        Laporan::factory()->wbs()->for($nilai)->create(['dibuat_pada' => '2025-12-01 02:00:00']);

        $baris = $this->rekap->wbsPerKategoriPerTriwulan(User::factory()->peran(Peran::KetuaUmum)->create(), 2026);
        $perKategori = collect($baris)->keyBy('kategori');

        $this->assertSame([1 => 3, 2 => null, 3 => null, 4 => null], $perKategori['Penyalahgunaan dana']['triwulan']);
        // 30 Sep 17.00 UTC = 1 Okt WITA, sehingga masuk triwulan 4.
        $this->assertSame([1 => null, 2 => null, 3 => null, 4 => 4], $perKategori['Kecurangan nilai']['triwulan']);
        $this->assertSame(['kategori', 'triwulan'], array_keys($baris[0]));
    }

    /**
     * Super Admin, Admin, PIC, Tim WBS, Kepala Sekolah, dan Komite Etik tidak mendapat angka WBS agregat.
     */
    #[DataProvider('peranTanpaRekapWbs')]
    public function test_roles_outside_a6_and_a7_get_no_wbs_aggregate(Peran $peran): void
    {
        Laporan::factory()->wbs()->count(5)->for(Kategori::factory()->wbs())->create();
        $user = User::factory()->peran($peran)->create(['unit_id' => $peran->wajibUnit() ? $this->sd->id : null]);

        $this->assertSame([], $this->rekap->wbsPerKategoriPerTriwulan($user, 2026));
        $this->assertSame(0, Laporan::query()->rekapWbsBagi($user)->count());
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranTanpaRekapWbs(): array
    {
        return collect([Peran::SuperAdmin, Peran::AdminPengaduan, Peran::PicUnit, Peran::KetuaTimWbs, Peran::AnggotaTimWbs, Peran::KepalaSekolah, Peran::KomiteEtik])
            ->mapWithKeys(fn (Peran $peran): array => [$peran->value => [$peran]])
            ->all();
    }

    public function test_super_admin_sees_no_report_numbers_only_locked_accounts(): void
    {
        Laporan::factory()->count(2)->create();
        Laporan::factory()->wbs()->create();
        User::factory()->terkunci()->create();
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->create();

        $this->assertSame(['Akun terkunci'], array_column($this->rekap->ringkasan($superAdmin), 'label'));
        $this->assertSame(1, $this->kartu($superAdmin, 'Akun terkunci'));
        $this->assertSame(0, Laporan::query()->rekapPengaduanBagi($superAdmin)->count());
        $this->assertSame([], $this->rekap->pengaduanPerUnitPerBulan($superAdmin, 2026));
    }

    public function test_wbs_team_counts_follow_the_recipient_matrix(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->create();
        Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::GuruPegawai]);
        Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::Pengawas]);

        $this->assertSame(1, $this->kartu($ketua, 'Laporan WBS baru'));
    }

    public function test_school_head_counts_only_redirects_they_may_open(): void
    {
        $kepsek = User::factory()->peran(Peran::KepalaSekolah)->for($this->sd)->create();
        Pengalihan::factory()->create(['tujuan' => TujuanPengalihan::KepalaSekolah, 'unit_tujuan_id' => $this->sd->id]);
        Pengalihan::factory()->create(['tujuan' => TujuanPengalihan::KepalaSekolah, 'unit_tujuan_id' => $this->smp->id]);
        Pengalihan::factory()->create(['tujuan' => TujuanPengalihan::KomiteEtik]);

        $this->assertSame(1, $this->kartu($kepsek, 'Pengalihan belum dikonfirmasi'));
    }

    public function test_complaint_recap_groups_by_unit_and_category_with_average_and_late_count(): void
    {
        $kategori = Kategori::factory()->create(['nama' => 'Fasilitas']);
        $tepat = Laporan::factory()->berstatus(StatusLaporan::Selesai)->for($this->sd)->for($kategori)->create(['dibuat_pada' => '2026-11-01 00:00:00', 'batas_waktu' => '2026-11-10 00:00:00']);
        $tepat->riwayatStatus()->create(['status_baru' => StatusLaporan::Selesai, 'tampil_ke_pelapor' => false])->forceFill(['waktu' => '2026-11-03 00:00:00'])->save();
        $telat = Laporan::factory()->berstatus(StatusLaporan::Selesai)->for($this->sd)->for($kategori)->create(['dibuat_pada' => '2026-11-01 00:00:00', 'batas_waktu' => '2026-11-02 00:00:00']);
        $telat->riwayatStatus()->create(['status_baru' => StatusLaporan::Selesai, 'tampil_ke_pelapor' => false])->forceFill(['waktu' => '2026-11-05 00:00:00'])->save();
        Laporan::factory()->berstatus(StatusLaporan::Diproses)->for($this->sd)->for($kategori)->create(['dibuat_pada' => '2026-11-02 00:00:00', 'batas_waktu' => '2026-11-10 00:00:00']);
        Laporan::factory()->for($this->smp)->for($kategori)->create(['dibuat_pada' => '2026-11-02 00:00:00']);
        Laporan::factory()->for($this->sd)->for($kategori)->create(['dibuat_pada' => '2026-09-01 00:00:00']);

        $adminSd = User::factory()->peran(Peran::AdminPengaduan)->for($this->sd)->create();
        $baris = $this->rekap->rekapPengaduan($adminSd, ['dari' => '2026-11-01', 'sampai' => '2026-11-30']);

        $this->assertSame([[
            'unit' => 'SD YPVDP',
            'kategori' => 'Fasilitas',
            'jumlah' => 3,
            'selesai' => 2,
            'rata_rata_hari' => 3.0,
            'lewat_sla' => 2,
        ]], $baris);

        $this->assertSame([], $this->rekap->rekapPengaduan($adminSd, ['dari' => '2026-11-01', 'sampai' => '2026-11-30', 'unit_id' => $this->smp->id]));
    }

    public function test_complaints_moved_to_wbs_are_not_counted_as_unfinished_complaints(): void
    {
        $kategori = Kategori::factory()->create(['nama' => 'Fasilitas']);
        Laporan::factory()->berstatus(StatusLaporan::Diproses)->for($this->sd)->for($kategori)->create(['dibuat_pada' => '2026-11-02 00:00:00', 'batas_waktu' => '2026-11-10 00:00:00']);
        Laporan::factory()->berstatus(StatusLaporan::DipindahKeWbs)->for($this->sd)->for($kategori)->create(['dibuat_pada' => '2026-11-03 00:00:00', 'batas_waktu' => '2026-11-04 00:00:00']);
        Laporan::factory()->berstatus(StatusLaporan::DipindahKeWbs)->for($this->smp)->for($kategori)->create(['dibuat_pada' => '2026-11-03 00:00:00']);

        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();
        $baris = $this->rekap->rekapPengaduan($admin, ['dari' => '2026-11-01', 'sampai' => '2026-11-30']);

        $this->assertSame([['unit' => 'SD YPVDP', 'kategori' => 'Fasilitas', 'jumlah' => 1, 'selesai' => 0, 'rata_rata_hari' => null, 'lewat_sla' => 1]], $baris);
    }

    public function test_decimals_use_the_indonesian_format(): void
    {
        $this->assertSame('0,2', RekapLaporan::angka(0.2));
        $this->assertSame('1.234,5', RekapLaporan::angka(1234.5));
        $this->assertSame('3,0', RekapLaporan::angka(3.0));
        $this->assertSame('-', RekapLaporan::angka(null));
    }

    private function kartu(User $user, string $label): ?int
    {
        return collect($this->rekap->ringkasan($user))->firstWhere('label', $label)['nilai'] ?? null;
    }
}
