<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\AksiAudit;
use App\Enums\StatusLaporan;
use App\Models\Laporan;
use App\Services\Pengaduan\KonfirmasiPelapor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutupPengaduanOtomatisTest extends TestCase
{
    use RefreshDatabase;

    public function test_closes_after_seven_calendar_days_counted_by_the_wita_date(): void
    {
        // Konfirmasi diminta Rabu 18 Nov 2026 07.46 WITA (= Selasa 17 Nov 23.46 UTC). Pelapor boleh menanggapi
        // sampai Rabu 25 Nov 23.59 WITA. Bila dihitung dari tanggal UTC, pengaduan keliru ditutup Rabu pagi.
        $laporan = $this->menungguKonfirmasiSejak('2026-11-17 23:46:00');

        $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00', 'Asia/Makassar')->utc());
        $this->artisan('sigap:tutup-pengaduan-otomatis')->assertSuccessful();
        $this->assertSame(StatusLaporan::MenungguKonfirmasi, $laporan->fresh()->status);

        $this->travelTo(CarbonImmutable::parse('2026-11-25 23:59', 'Asia/Makassar')->utc());
        $this->artisan('sigap:tutup-pengaduan-otomatis')->assertSuccessful();
        $this->assertSame(StatusLaporan::MenungguKonfirmasi, $laporan->fresh()->status);

        $this->travelTo(CarbonImmutable::parse('2026-11-26 00:10', 'Asia/Makassar')->utc());
        $this->artisan('sigap:tutup-pengaduan-otomatis')
            ->expectsOutput('Pengaduan ditutup otomatis: 1.')
            ->assertSuccessful();

        $this->assertSame(StatusLaporan::Selesai, $laporan->fresh()->status);
        $riwayat = $laporan->riwayatStatus()->latest('id')->first();
        $this->assertNull($riwayat->oleh_user_id);
        $this->assertSame(KonfirmasiPelapor::CATATAN_TUTUP_OTOMATIS, $riwayat->catatan);
        $this->assertTrue($riwayat->tampil_ke_pelapor);
        $this->assertDatabaseHas('log_audit', [
            'user_id' => null,
            'aksi' => AksiAudit::MenutupPengaduanOtomatis->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
    }

    public function test_leaves_other_statuses_and_wbs_reports_alone(): void
    {
        $diproses = Laporan::factory()->berstatus(StatusLaporan::Diproses)->create();
        $wbs = Laporan::factory()->wbs()->create();

        $this->travelTo(now()->addMonth());
        $this->artisan('sigap:tutup-pengaduan-otomatis')
            ->expectsOutput('Pengaduan ditutup otomatis: 0.')
            ->assertSuccessful();

        $this->assertSame(StatusLaporan::Diproses, $diproses->fresh()->status);
        $this->assertSame(StatusLaporan::Diterima, $wbs->fresh()->status);
    }

    public function test_counts_from_the_latest_request_after_the_reporter_rejected_an_earlier_result(): void
    {
        $laporan = $this->menungguKonfirmasiSejak('2026-11-02 01:00:00');
        $laporan->riwayatStatus()->forceCreate(['status_baru' => StatusLaporan::Diproses, 'waktu' => '2026-11-05 01:00:00']);
        $laporan->riwayatStatus()->forceCreate(['status_baru' => StatusLaporan::MenungguKonfirmasi, 'waktu' => '2026-11-20 01:00:00']);

        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00', 'Asia/Makassar')->utc());
        $this->artisan('sigap:tutup-pengaduan-otomatis')->assertSuccessful();

        $this->assertSame(StatusLaporan::MenungguKonfirmasi, $laporan->fresh()->status);
    }

    private function menungguKonfirmasiSejak(string $waktuUtc): Laporan
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::MenungguKonfirmasi)->create();
        $laporan->riwayatStatus()->forceCreate([
            'status_lama' => StatusLaporan::Diproses,
            'status_baru' => StatusLaporan::MenungguKonfirmasi,
            'waktu' => CarbonImmutable::parse($waktuUtc, 'UTC'),
        ]);

        return $laporan;
    }
}
