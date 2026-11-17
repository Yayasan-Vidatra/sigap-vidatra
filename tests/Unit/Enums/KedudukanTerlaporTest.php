<?php

namespace Tests\Unit\Enums;

use App\Enums\KedudukanTerlapor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KedudukanTerlaporTest extends TestCase
{
    /**
     * Matriks Rancangan r1 Bagian 3.1: kolom penelaah selain Tim WBS.
     *
     * @return array<string, array{KedudukanTerlapor, bool}>
     */
    public static function matriks(): array
    {
        return [
            'guru, pegawai, kepala sekolah' => [KedudukanTerlapor::GuruPegawai, false],
            'pengurus' => [KedudukanTerlapor::Pengurus, false],
            'spi atau tim wbs' => [KedudukanTerlapor::SpiTimWbs, true],
            'pengawas' => [KedudukanTerlapor::Pengawas, true],
            'pembina' => [KedudukanTerlapor::Pembina, true],
            'tidak tahu' => [KedudukanTerlapor::TidakTahu, false],
        ];
    }

    #[DataProvider('matriks')]
    public function test_closes_laporan_to_ketua_tim_wbs_per_matriks(KedudukanTerlapor $kedudukan, bool $tertutup): void
    {
        $this->assertSame($tertutup, $kedudukan->tertutupBagiKetuaTimWbs());
    }
}
