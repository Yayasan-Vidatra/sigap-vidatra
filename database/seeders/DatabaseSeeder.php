<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Data induk unit dan kategori. Akun uji lokal dibuat terpisah lewat AkunUjiSeeder.
     */
    public function run(): void
    {
        $this->call([
            UnitSeeder::class,
            KategoriSeeder::class,
        ]);
    }
}
