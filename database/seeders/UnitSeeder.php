<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Empat unit Yayasan (Rancangan r1 Bagian 1 dan 10), lalu PT Vidatra Jaya Makmur sebagai unit khusus WBS tepat di bawah
 * Pelaksana Harian (revisi 20-11). Nama pimpinan PT VJM diisi lewat Pengaturan > Unit, tidak di sini. Aman dijalankan ulang.
 */
class UnitSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const UNIT = ['SD YPVDP', 'SMP YPVDP', 'SMA YPVDP', 'Pelaksana Harian'];

    /** Unit yang hanya tampil sebagai tempat kejadian WBS. */
    public const UNIT_KHUSUS_WBS = ['PT Vidatra Jaya Makmur'];

    public function run(): void
    {
        foreach (self::UNIT as $nama) {
            Unit::firstOrCreate(['nama' => $nama], ['aktif' => true]);
        }

        foreach (self::UNIT_KHUSUS_WBS as $nama) {
            Unit::firstOrCreate(['nama' => $nama], ['aktif' => true, 'khusus_wbs' => true]);
        }
    }
}
