<?php

namespace Tests\Feature\Services\Pengaduan;

use App\Enums\AksiAudit;
use App\Enums\StatusLaporan;
use App\Events\HasilDitolakPelapor;
use App\Events\StatusLaporanBerubah;
use App\Exceptions\AksiPengaduanTidakSah;
use App\Models\Disposisi;
use App\Models\Laporan;
use App\Services\Pengaduan\KonfirmasiPelapor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class KonfirmasiPelaporTest extends TestCase
{
    use RefreshDatabase;

    private KonfirmasiPelapor $konfirmasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->konfirmasi = app(KonfirmasiPelapor::class);
    }

    public function test_reporter_confirmation_closes_the_complaint_without_emailing_the_reporter_again(): void
    {
        Event::fake([StatusLaporanBerubah::class]);
        $laporan = Laporan::factory()->berstatus(StatusLaporan::MenungguKonfirmasi)->create();

        $this->konfirmasi->konfirmasi($laporan);

        $this->assertSame(StatusLaporan::Selesai, $laporan->fresh()->status);
        $this->assertDatabaseHas('riwayat_status', [
            'laporan_id' => $laporan->id,
            'status_baru' => StatusLaporan::Selesai->value,
            'oleh_user_id' => null,
        ]);
        $this->assertDatabaseHas('log_audit', ['user_id' => null, 'aksi' => AksiAudit::PelaporMengonfirmasiHasil->value]);
        Event::assertDispatched(StatusLaporanBerubah::class, fn (StatusLaporanBerubah $event): bool => ! $event->beritahuPelapor);
    }

    public function test_rejecting_the_result_returns_the_complaint_to_in_process_and_notifies_admin(): void
    {
        Event::fake([HasilDitolakPelapor::class, StatusLaporanBerubah::class]);
        $laporan = Laporan::factory()->berstatus(StatusLaporan::MenungguKonfirmasi)->create();
        $disposisi = Disposisi::factory()->for($laporan)->create(['unit_id' => $laporan->unit_id, 'selesai_pada' => now()]);

        $this->konfirmasi->tolakHasil($laporan, ' Keran masih bocor. ');

        $this->assertSame(StatusLaporan::Diproses, $laporan->fresh()->status);
        $this->assertNull($disposisi->fresh()->selesai_pada);
        $riwayat = $laporan->riwayatStatus()->latest('id')->first();
        $this->assertSame('Keran masih bocor.', $riwayat->catatan);
        $this->assertTrue($riwayat->tampil_ke_pelapor);
        Event::assertDispatched(HasilDitolakPelapor::class, fn (HasilDitolakPelapor $event): bool => $event->laporan->is($laporan));
        Event::assertDispatched(StatusLaporanBerubah::class, fn (StatusLaporanBerubah $event): bool => ! $event->beritahuPelapor);
    }

    public function test_rejecting_the_result_requires_a_reason(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::MenungguKonfirmasi)->create();

        $this->expectExceptionObject(AksiPengaduanTidakSah::alasanTolakHasilWajib());

        $this->konfirmasi->tolakHasil($laporan, '   ');
    }

    #[TestWith([StatusLaporan::Diproses])]
    #[TestWith([StatusLaporan::Selesai])]
    #[TestWith([StatusLaporan::Ditolak])]
    public function test_reporter_can_only_respond_while_waiting_for_confirmation(StatusLaporan $status): void
    {
        $laporan = Laporan::factory()->berstatus($status)->create();

        foreach ([fn () => $this->konfirmasi->konfirmasi($laporan), fn () => $this->konfirmasi->tolakHasil($laporan, 'Belum.')] as $aksi) {
            try {
                $aksi();
                $this->fail('Tanggapan pelapor di luar Menunggu Konfirmasi seharusnya ditolak.');
            } catch (AksiPengaduanTidakSah) {
                // Diharapkan.
            }
        }

        $this->assertSame($status, $laporan->fresh()->status);
        $this->assertDatabaseCount('riwayat_status', 0);
    }

    public function test_rating_is_saved_once_after_the_complaint_is_finished(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Selesai)->create();

        $this->konfirmasi->beriPenilaian($laporan, 4);

        $this->assertSame(4, $laporan->fresh()->penilaian);
        $this->assertDatabaseHas('log_audit', ['user_id' => null, 'aksi' => AksiAudit::PelaporMemberiPenilaian->value]);

        $this->expectExceptionObject(AksiPengaduanTidakSah::penilaianTidakTersedia());

        try {
            $this->konfirmasi->beriPenilaian($laporan, 1);
        } finally {
            $this->assertSame(4, $laporan->fresh()->penilaian);
        }
    }

    #[TestWith([StatusLaporan::Diproses])]
    #[TestWith([StatusLaporan::MenungguKonfirmasi])]
    #[TestWith([StatusLaporan::Ditolak])]
    public function test_rating_is_only_possible_after_the_complaint_is_finished(StatusLaporan $status): void
    {
        $laporan = Laporan::factory()->berstatus($status)->create();

        $this->expectExceptionObject(AksiPengaduanTidakSah::penilaianTidakTersedia());

        try {
            $this->konfirmasi->beriPenilaian($laporan, 5);
        } finally {
            $this->assertNull($laporan->fresh()->penilaian);
        }
    }

    #[TestWith([0])]
    #[TestWith([6])]
    public function test_rating_must_be_between_one_and_five(int $nilai): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Selesai)->create();

        $this->expectExceptionObject(AksiPengaduanTidakSah::penilaianDiLuarRentang());

        $this->konfirmasi->beriPenilaian($laporan, $nilai);
    }

    public function test_a_wbs_report_cannot_be_confirmed_through_the_complaint_flow(): void
    {
        $wbs = Laporan::factory()->wbs()->berstatus(StatusLaporan::Diterima)->create();

        $this->expectException(AksiPengaduanTidakSah::class);

        $this->konfirmasi->konfirmasi($wbs);
    }
}
