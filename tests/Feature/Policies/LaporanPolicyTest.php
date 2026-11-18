<?php

namespace Tests\Feature\Policies;

use App\Enums\KedudukanTerlapor;
use App\Enums\Peran;
use App\Models\Disposisi;
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

    public function test_pic_unit_opens_only_complaints_disposed_to_their_unit(): void
    {
        $unit = Unit::factory()->create();
        $pic = User::factory()->peran(Peran::PicUnit)->for($unit)->create();
        $didisposisikan = Laporan::factory()->for($unit)->create();
        Disposisi::factory()->for($didisposisikan)->for($unit)->create();
        $belumDisposisi = Laporan::factory()->for($unit)->create();
        $unitLain = Laporan::factory()->create();
        Disposisi::factory()->for($unitLain)->create(['unit_id' => $unitLain->unit_id]);
        $wbs = Laporan::factory()->wbs()->for($unit)->create();

        $this->assertTrue($pic->can('lihatPengaduanUnit', Laporan::class));
        $this->assertTrue($pic->can('tindakLanjutiPengaduan', $didisposisikan));
        $this->assertFalse($pic->can('tindakLanjutiPengaduan', $belumDisposisi));
        $this->assertFalse($pic->can('tindakLanjutiPengaduan', $unitLain));
        $this->assertFalse($pic->can('tindakLanjutiPengaduan', $wbs));
    }

    /**
     * @return array<string, array{Peran}>
     */
    public static function peranSelainPic(): array
    {
        return collect(Peran::cases())
            ->reject(fn (Peran $peran): bool => $peran === Peran::PicUnit)
            ->mapWithKeys(fn (Peran $peran): array => [$peran->value => [$peran]])
            ->all();
    }

    #[DataProvider('peranSelainPic')]
    public function test_other_roles_cannot_open_the_unit_page_or_follow_up(Peran $peran): void
    {
        $laporan = Laporan::factory()->create();
        Disposisi::factory()->for($laporan)->create(['unit_id' => $laporan->unit_id]);
        $user = User::factory()->peran($peran)->create(['unit_id' => $peran->bolehUnit() ? $laporan->unit_id : null]);

        $this->assertFalse($user->can('lihatPengaduanUnit', Laporan::class));
        $this->assertFalse($user->can('tindakLanjutiPengaduan', $laporan));
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

    /**
     * Matriks penerima Bagian 3.1: kedudukan terlapor => peran yang boleh membuka laporan WBS.
     *
     * @return array<string, array{KedudukanTerlapor|null, list<Peran>}>
     */
    public static function matriksPenerima(): array
    {
        $timWbs = [Peran::KetuaTimWbs, Peran::AnggotaTimWbs];
        $pembina = [Peran::KetuaPembina, Peran::Pembina];

        return [
            'guru/pegawai' => [KedudukanTerlapor::GuruPegawai, $timWbs],
            'pengurus' => [KedudukanTerlapor::Pengurus, $timWbs],
            'tidak tahu' => [KedudukanTerlapor::TidakTahu, $timWbs],
            'kosong' => [null, $timWbs],
            'spi/tim wbs' => [KedudukanTerlapor::SpiTimWbs, [Peran::DewanPengawas]],
            'pengawas' => [KedudukanTerlapor::Pengawas, $pembina],
            'pembina' => [KedudukanTerlapor::Pembina, $pembina],
        ];
    }

    /**
     * @param  list<Peran>  $boleh
     */
    #[DataProvider('matriksPenerima')]
    public function test_wbs_report_opens_only_for_roles_in_the_recipient_matrix(?KedudukanTerlapor $kedudukan, array $boleh): void
    {
        $laporan = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => $kedudukan]);

        foreach (Peran::cases() as $peran) {
            $user = User::factory()->peran($peran)->create(['unit_id' => $peran->wajibUnit() ? Unit::factory()->create()->id : null]);

            $this->assertSame(in_array($peran, $boleh, true), $user->can('bukaLaporanWbs', $laporan), "Peran {$peran->value}");
        }
    }

    public function test_super_admin_never_opens_wbs_content(): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->create();

        foreach (KedudukanTerlapor::cases() as $kedudukan) {
            $this->assertFalse($superAdmin->can('bukaLaporanWbs', Laporan::factory()->wbs()->create(['kedudukan_terlapor' => $kedudukan])));
        }

        $this->assertSame(0, Laporan::query()->wbsTerbukaBagi($superAdmin)->count());
    }

    public function test_ketua_tim_wbs_never_sees_reports_about_spi_pengawas_or_pembina(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->create();
        $terbuka = Laporan::factory()->wbs()->create(['kedudukan_terlapor' => KedudukanTerlapor::GuruPegawai]);
        foreach ([KedudukanTerlapor::SpiTimWbs, KedudukanTerlapor::Pengawas, KedudukanTerlapor::Pembina] as $kedudukan) {
            Laporan::factory()->wbs()->create(['kedudukan_terlapor' => $kedudukan]);
        }

        $this->assertSame([$terbuka->id], Laporan::query()->wbsTerbukaBagi($ketua)->pluck('id')->all());
    }

    public function test_pengaduan_never_opens_through_the_wbs_ability(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->create();

        $this->assertFalse($ketua->can('bukaLaporanWbs', Laporan::factory()->create()));
    }
}
