<?php

namespace Tests\Feature\Services\Lacak;

use App\Enums\StatusLaporan;
use App\Models\Laporan;
use App\Services\Lacak\TahapanLaporan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TahapanLaporanTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_finished_complaint_has_every_stage_done(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Selesai)->create();

        $this->assertSame(
            ['selesai', 'selesai', 'selesai', 'selesai', 'selesai'],
            array_column(TahapanLaporan::untuk($laporan), 'keadaan'),
        );
    }

    public function test_a_rejected_complaint_ends_with_its_final_status_after_the_stages_it_passed(): void
    {
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Ditolak)->create();
        $laporan->riwayatStatus()->create(['status_lama' => StatusLaporan::Diterima, 'status_baru' => StatusLaporan::Ditolak]);

        $this->assertSame(['Diterima', 'Ditolak'], array_column(TahapanLaporan::untuk($laporan->refresh()), 'label'));
    }

    public function test_wbs_stages_treat_an_extended_investigation_as_the_investigation_stage(): void
    {
        $laporan = Laporan::factory()->wbs()->berstatus(StatusLaporan::DiinvestigasiDiperpanjang)->create();

        $this->assertSame(
            [['Diterima', 'selesai'], ['Ditelaah', 'selesai'], ['Diinvestigasi', 'berjalan'], ['Selesai', 'menunggu']],
            array_map(fn (array $tahap): array => [$tahap['label'], $tahap['keadaan']], TahapanLaporan::untuk($laporan)),
        );
    }
}
