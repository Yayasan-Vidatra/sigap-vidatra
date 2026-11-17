<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Unit;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_four_yayasan_units_once(): void
    {
        $this->seed(UnitSeeder::class);
        $this->seed(UnitSeeder::class);

        $this->assertSame(
            ['Pelaksana Harian', 'SD YPVDP', 'SMA YPVDP', 'SMP YPVDP'],
            Unit::query()->orderBy('nama')->pluck('nama')->all(),
        );
        $this->assertSame(4, Unit::query()->where('aktif', true)->count());
    }
}
