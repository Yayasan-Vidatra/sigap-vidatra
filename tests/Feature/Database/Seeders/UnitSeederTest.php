<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Unit;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_four_yayasan_units_and_pt_vjm_below_pelaksana_harian_once(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(UnitSeeder::class);

        $this->assertSame(
            ['SD YPVDP', 'SMP YPVDP', 'SMA YPVDP', 'Pelaksana Harian', 'PT Vidatra Jaya Makmur'],
            Unit::query()->orderBy('id')->pluck('nama')->all(),
        );
        $this->assertSame(5, Unit::query()->where('aktif', true)->count());
        $this->assertSame(['PT Vidatra Jaya Makmur'], Unit::query()->where('khusus_wbs', true)->pluck('nama')->all());
        $this->assertNull(Unit::query()->where('khusus_wbs', true)->value('nama_pimpinan'));
    }
}
