<?php

namespace Tests\Feature\Filament\Pages;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Filament\Pages\RekapEkspor;
use App\Models\IdentitasPelapor;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Pesan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Rekap\EksporRekap;
use App\Services\Rekap\RekapLaporan;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MembacaPdf;
use Tests\TestCase;

/**
 * H-18 Rekap dan Ekspor (US-23, UC-14, F-14).
 */
class RekapEksporTest extends TestCase
{
    use MembacaPdf, RefreshDatabase;

    private Unit $sd;

    private Unit $sma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-19 02:00:00'));
        $this->sd = Unit::factory()->create(['nama' => 'SD YPVDP']);
        $this->sma = Unit::factory()->create(['nama' => 'SMA YPVDP']);

        $kategori = Kategori::factory()->create(['nama' => 'Fasilitas']);
        $pengaduan = Laporan::factory()->for($this->sd)->for($kategori)->create(['judul' => 'Atap kelas bocor', 'kronologi' => 'Atap kelas 3 bocor sejak minggu lalu.']);
        IdentitasPelapor::factory()->for($pengaduan)->create(['nama' => 'Sari Pelapor']);
        Pesan::factory()->for($pengaduan)->create(['isi' => 'Catatan internal rahasia', 'internal' => true]);
        Laporan::factory()->for($this->sma)->for($kategori)->create();

        $wbs = Laporan::factory()->wbs()->count(3)->for(Kategori::factory()->wbs()->create(['nama' => 'Penyalahgunaan dana']))->create([
            'judul' => 'Dana BOS dipakai pribadi',
            'dibuat_pada' => '2026-08-01 02:00:00',
        ]);
        $wbs->each(fn (Laporan $laporan) => $laporan->terlapor()->create(['nama' => 'Budi Bendahara']));
    }

    /**
     * @return array<string, array{Peran, bool}>
     */
    public static function peran(): array
    {
        return [
            'admin pengaduan' => [Peran::AdminPengaduan, true],
            'ketua umum' => [Peran::KetuaUmum, true],
            'pengurus harian' => [Peran::PengurusHarian, true],
            'dewan pengawas' => [Peran::DewanPengawas, true],
            'pembina' => [Peran::Pembina, true],
            'pic unit' => [Peran::PicUnit, false],
            'super admin' => [Peran::SuperAdmin, false],
            'ketua tim wbs' => [Peran::KetuaTimWbs, false],
            'kepala sekolah' => [Peran::KepalaSekolah, false],
            'komite etik' => [Peran::KomiteEtik, false],
        ];
    }

    #[DataProvider('peran')]
    public function test_only_admin_leadership_and_result_recipients_open_the_recap(Peran $peran, bool $boleh): void
    {
        $user = $this->pengguna($peran);

        $this->actingAs($user)->get('/panel/rekap')->assertStatus($boleh ? 200 : 403);

        if (! $boleh) {
            try {
                app(EksporRekap::class)->excel($user, $this->periode());
                $this->fail('Peran ini tidak boleh mengekspor rekap.');
            } catch (AuthorizationException) {
                $this->assertDatabaseMissing('log_audit', ['aksi' => AksiAudit::MengeksporRekap->value]);
            }
        }
    }

    public function test_school_admin_sees_only_their_school_and_no_wbs_section(): void
    {
        $this->actingAs($this->pengguna(Peran::AdminPengaduan, $this->sd));

        Livewire::test(RekapEkspor::class)
            ->assertSee('SD YPVDP')
            ->assertDontSee('SMA YPVDP')
            ->assertDontSee('Laporan WBS per kategori')
            ->assertDontSee('Penyalahgunaan dana');
    }

    public function test_leadership_sees_complaints_per_unit_and_wbs_counts_without_content(): void
    {
        $this->actingAs($this->pengguna(Peran::KetuaUmum));

        Livewire::test(RekapEkspor::class)
            ->assertSee('SD YPVDP')
            ->assertSee('SMA YPVDP')
            ->assertSee('Penyalahgunaan dana')
            ->assertSee('&lt; 3', false)
            ->assertDontSee('Dana BOS dipakai pribadi')
            ->assertDontSee('Budi Bendahara')
            ->assertDontSee('Atap kelas bocor')
            ->assertDontSee('Sari Pelapor');
    }

    public function test_average_days_use_a_decimal_comma_on_screen_and_in_the_pdf(): void
    {
        $kategori = Kategori::factory()->create(['nama' => 'Kebersihan']);
        $cepat = Laporan::factory()->berstatus(StatusLaporan::Selesai)->for($this->sd)->for($kategori)->create(['dibuat_pada' => '2026-11-10 00:00:00']);
        $cepat->riwayatStatus()->create(['status_baru' => StatusLaporan::Selesai, 'tampil_ke_pelapor' => false])->forceFill(['waktu' => '2026-11-10 04:48:00'])->save();
        $user = $this->pengguna(Peran::AdminPengaduan);
        $this->actingAs($user);

        Livewire::test(RekapEkspor::class)
            ->assertSeeHtml('>0,2</td>')
            ->assertDontSeeHtml('>0.2</td>')
            ->assertSee(RekapLaporan::CATATAN_PINDAH_WBS);

        $html = view('pdf.rekap', [...app(EksporRekap::class)->data($user, $this->periode()), 'minimal' => 3])->render();
        $this->assertStringContainsString('>0,2<', $html);
        $this->assertStringNotContainsString('0.2', $html);
        $this->assertStringContainsString(RekapLaporan::CATATAN_PINDAH_WBS, $html);
        $this->assertStringContainsString('0,2', $this->teksPdf(app(EksporRekap::class)->pdf($user, $this->periode())));
    }

    public function test_excel_export_holds_only_numbers_and_is_logged(): void
    {
        $user = $this->pengguna(Peran::DewanPengawas);

        $path = app(EksporRekap::class)->excel($user, [...$this->periode(), 'tahun_wbs' => '2026']);
        $isi = $this->bacaXlsx($path);
        @unlink($path);

        $this->assertStringContainsString('SD YPVDP', $isi);
        $this->assertStringContainsString('Penyalahgunaan dana', $isi);
        $this->assertStringContainsString('< 3', $isi);
        foreach (['Dana BOS dipakai pribadi', 'Budi Bendahara', 'Atap kelas bocor', 'Sari Pelapor', 'Catatan internal rahasia', 'WBS-', 'PGD-'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $isi);
        }

        $this->assertDatabaseHas('log_audit', [
            'user_id' => $user->id,
            'aksi' => AksiAudit::MengeksporRekap->value,
            'objek' => 'rekap:xlsx:2026-01-01..2026-11-30:unit=semua:kategori=semua:wbs=2026',
        ]);
    }

    public function test_pdf_export_holds_only_numbers_and_is_logged(): void
    {
        $user = $this->pengguna(Peran::AdminPengaduan);
        $ekspor = app(EksporRekap::class);

        $this->assertStringStartsWith('%PDF', $ekspor->pdf($user, $this->periode()));
        $this->assertDatabaseHas('log_audit', ['user_id' => $user->id, 'aksi' => AksiAudit::MengeksporRekap->value, 'objek' => 'rekap:pdf:2026-01-01..2026-11-30:unit=semua:kategori=semua']);

        $html = view('pdf.rekap', [...$ekspor->data($user, $this->periode()), 'minimal' => 3])->render();
        $this->assertStringContainsString('SMA YPVDP', $html);
        foreach (['Penyalahgunaan dana', 'Dana BOS dipakai pribadi', 'Atap kelas bocor', 'Sari Pelapor', 'Catatan internal rahasia'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $html);
        }
    }

    public function test_export_buttons_download_a_file(): void
    {
        $this->actingAs($this->pengguna(Peran::AdminPengaduan));

        Livewire::test(RekapEkspor::class)->callAction('eksporExcel')->assertFileDownloaded();
        Livewire::test(RekapEkspor::class)->callAction('eksporPdf')->assertFileDownloaded();

        $this->assertDatabaseCount('log_audit', 2);
    }

    public function test_an_invalid_period_is_rejected_and_not_logged(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(EksporRekap::class)->excel($this->pengguna(Peran::AdminPengaduan), ['dari' => '2026-12-01', 'sampai' => '2026-01-01']);
        } finally {
            $this->assertDatabaseMissing('log_audit', ['aksi' => AksiAudit::MengeksporRekap->value]);
        }
    }

    /**
     * @return array{dari: string, sampai: string}
     */
    private function periode(): array
    {
        return ['dari' => '2026-01-01', 'sampai' => '2026-11-30'];
    }

    private function pengguna(Peran $peran, ?Unit $unit = null): User
    {
        return User::factory()->peran($peran)->denganMfa()->create([
            'unit_id' => $unit?->id ?? ($peran->wajibUnit() ? $this->sd->id : null),
        ]);
    }

    private function bacaXlsx(string $path): string
    {
        $reader = new Reader;
        $reader->open($path);
        $isi = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $baris) {
                $isi[] = implode("\t", array_map('strval', $baris->toArray()));
            }
        }

        $reader->close();

        return implode("\n", $isi);
    }
}
