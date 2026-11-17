<?php

namespace Tests\Feature\Enums;

use App\Enums\TahapWbs;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TahapWbsTest extends TestCase
{
    /**
     * Tenggat Rancangan r1 Bagian 2, dihitung dari Senin 16 November 2026 pukul 09.00.
     *
     * @return array<string, array{TahapWbs, string}>
     */
    public static function tenggat(): array
    {
        return [
            'telaah 5 hari kerja' => [TahapWbs::Telaah, '2026-11-23 09:00'],
            'investigasi 30 hari kerja' => [TahapWbs::Investigasi, '2026-12-28 09:00'],
            'perpanjangan 30 hari kerja' => [TahapWbs::Perpanjangan, '2026-12-28 09:00'],
        ];
    }

    #[DataProvider('tenggat')]
    public function test_deadline_counts_working_days_from_stage_start(TahapWbs $tahap, string $batas): void
    {
        $mulai = CarbonImmutable::parse('2026-11-16 09:00');

        $this->assertSame($batas, $tahap->batasDari($mulai)->format('Y-m-d H:i'));
    }

    public function test_deadline_follows_configuration(): void
    {
        config(['sigap.wbs.telaah_hari_kerja' => 3]);

        $batas = TahapWbs::Telaah->batasDari(CarbonImmutable::parse('2026-11-20 09:00'));

        $this->assertSame('2026-11-25 09:00', $batas->format('Y-m-d H:i'));
    }
}
