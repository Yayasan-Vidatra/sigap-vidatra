<?php

namespace Tests\Feature\Mail;

use App\Enums\StatusLaporan;
use App\Mail\StatusPengaduanBerubah;
use App\Models\IdentitasPelapor;
use App\Models\Laporan;
use App\Models\RiwayatStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusPengaduanBerubahTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_shows_code_status_wita_time_and_tracking_link_but_no_complaint_content(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::MenungguKonfirmasi)->create([
            'kode_lacak' => 'PGD-2611-7KQ2M9',
            'judul' => 'Toilet lantai 2 rusak',
            'kronologi' => 'Guru Pak Budi membiarkan keran bocor berminggu-minggu.',
        ]);
        IdentitasPelapor::factory()->for($laporan)->create(['nama' => 'Rina Kartika', 'no_hp' => '081234567890']);
        $riwayat = $this->riwayat($laporan, StatusLaporan::MenungguKonfirmasi, null, false);

        $surel = new StatusPengaduanBerubah($laporan->fresh(), $riwayat);

        $surel->assertHasSubject('Status pengaduan PGD-2611-7KQ2M9: Menunggu Konfirmasi');
        $surel->assertSeeInText('PGD-2611-7KQ2M9');
        $surel->assertSeeInText('18 November 2026, 07.46 WITA');
        $surel->assertSeeInText('7 hari');
        $surel->assertSeeInHtml(route('lacak'));
        $surel->assertDontSeeInText('Pak Budi');
        $surel->assertDontSeeInText('Rina Kartika');
        $surel->assertDontSeeInText('081234567890');
    }

    public function test_a_reporter_visible_note_is_included(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Ditolak)->create();
        $riwayat = $this->riwayat($laporan, StatusLaporan::Ditolak, 'Di luar kewenangan yayasan.', true);

        (new StatusPengaduanBerubah($laporan, $riwayat))->assertSeeInText('Di luar kewenangan yayasan.');
    }

    public function test_an_internal_note_is_never_included(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Selesai)->create();
        $riwayat = $this->riwayat($laporan, StatusLaporan::Selesai, 'Dikonfirmasi lewat telepon dengan Bu Rina, nomor 0812xxxx.', false);

        (new StatusPengaduanBerubah($laporan, $riwayat))->assertDontSeeInText('Bu Rina');
    }

    private function riwayat(Laporan $laporan, StatusLaporan $status, ?string $catatan, bool $tampil): RiwayatStatus
    {
        return $laporan->riwayatStatus()->forceCreate([
            'status_baru' => $status,
            'catatan' => $catatan,
            'tampil_ke_pelapor' => $tampil,
            'waktu' => CarbonImmutable::parse('2026-11-17 23:46:00', 'UTC'),
        ]);
    }
}
