<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Empat unit Yayasan (Rancangan r1 Bagian 1 dan 10). Aman dijalankan ulang.
 */
class UnitSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const UNIT = ['SD YPVDP', 'SMP YPVDP', 'SMA YPVDP', 'Pelaksana Harian'];

    public function run(): void
    {
        foreach (self::UNIT as $nama) {
            Unit::firstOrCreate(['nama' => $nama], ['aktif' => true]);
        }
    }
}
