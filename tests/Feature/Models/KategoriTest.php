<?php

namespace Tests\Feature\Models;

use App\Exceptions\PelanggaranIntegritasData;
use App\Models\Kategori;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KategoriTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengaduan_deadlines_count_working_days_only(): void
    {
        $kategori = Kategori::factory()->create(['sla_tanggapan_hari' => 2, 'sla_hari' => 14]);
        $diterimaJumat = CarbonImmutable::parse('2026-11-20 10:00');

        $this->assertSame('2026-11-24 10:00', $kategori->batasTanggapan($diterimaJumat)->format('Y-m-d H:i'));
        $this->assertSame('2026-12-10 10:00', $kategori->batasSelesai($diterimaJumat)->format('Y-m-d H:i'));
    }

    public function test_pengaduan_received_on_a_weekend_counts_from_monday(): void
    {
        $kategori = Kategori::factory()->create(['sla_tanggapan_hari' => 2]);

        $batas = $kategori->batasTanggapan(CarbonImmutable::parse('2026-11-21 08:00'));

        $this->assertSame('2026-11-24 08:00', $batas->format('Y-m-d H:i'));
    }

    public function test_pengaduan_kategori_without_sla_is_rejected(): void
    {
        $this->expectException(PelanggaranIntegritasData::class);

        try {
            Kategori::factory()->create(['sla_tanggapan_hari' => null]);
        } finally {
            $this->assertDatabaseCount('kategori', 0);
        }
    }

    public function test_wbs_kategori_with_sla_is_rejected(): void
    {
        $this->expectException(PelanggaranIntegritasData::class);

        try {
            Kategori::factory()->wbs()->create(['sla_hari' => 30]);
        } finally {
            $this->assertDatabaseCount('kategori', 0);
        }
    }

    public function test_wbs_kategori_has_no_sla_deadline(): void
    {
        $kategori = Kategori::factory()->wbs()->create();

        $this->expectException(PelanggaranIntegritasData::class);

        $kategori->batasSelesai(CarbonImmutable::parse('2026-11-20 10:00'));
    }
}
