<?php

namespace Tests\Feature\Models;

use App\Enums\JenisLaporan;
use App\Models\Laporan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

/**
 * Laporan::buatKodeLacak(): kode lacak acak dan unik (Rancangan r1 Bagian 9, US-07).
 */
class KodeLacakTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Laporan::pakaiPengacakKodeLacak(null);

        parent::tearDown();
    }

    public function test_pengaduan_code_has_the_month_and_six_random_characters(): void
    {
        $this->travelTo('2026-11-17 09:00');

        $kode = Laporan::buatKodeLacak(JenisLaporan::Pengaduan);

        $this->assertMatchesRegularExpression('/^PGD-2611-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{6}$/', $kode);
    }

    public function test_wbs_code_has_two_groups_of_four_random_characters_and_no_date(): void
    {
        $kode = Laporan::buatKodeLacak(JenisLaporan::Wbs);

        $this->assertMatchesRegularExpression('/^WBS-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{4}-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{4}$/', $kode);
    }

    public function test_consecutive_codes_are_not_sequential(): void
    {
        $kode = collect(range(1, 50))->map(fn (): string => Laporan::buatKodeLacak(JenisLaporan::Pengaduan));

        $this->assertCount(50, $kode->unique());
        $this->assertNotSame($kode->sort()->values()->all(), $kode->values()->all());
    }

    public function test_code_already_in_use_is_skipped(): void
    {
        $this->travelTo('2026-11-17 09:00');
        Laporan::pakaiPengacakKodeLacak(new Randomizer(new Mt19937(2026)));
        $kodePertama = Laporan::buatKodeLacak(JenisLaporan::Pengaduan);
        Laporan::factory()->create(['kode_lacak' => $kodePertama]);

        Laporan::pakaiPengacakKodeLacak(new Randomizer(new Mt19937(2026)));
        $kodeBaru = Laporan::buatKodeLacak(JenisLaporan::Pengaduan);

        $this->assertNotSame($kodePertama, $kodeBaru);
        $this->assertMatchesRegularExpression('/^PGD-2611-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{6}$/', $kodeBaru);
    }
}
