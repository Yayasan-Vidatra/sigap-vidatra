<?php

namespace Tests\Feature\Database\Seeders;

use App\Enums\JenisLaporan;
use App\Models\Kategori;
use Database\Seeders\KategoriSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KategoriSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_sample_categories_for_both_kanal(): void
    {
        $this->seed(KategoriSeeder::class);

        $this->assertSame(5, Kategori::query()->where('jenis', JenisLaporan::Pengaduan)->count());
        $this->assertSame(6, Kategori::query()->where('jenis', JenisLaporan::Wbs)->count());
        $this->assertDatabaseHas('kategori', ['jenis' => 'pengaduan', 'nama' => 'Kebersihan', 'sla_hari' => 14]);
        $this->assertDatabaseHas('kategori', ['jenis' => 'wbs', 'nama' => 'Suap atau gratifikasi', 'sla_hari' => 30]);
    }

    public function test_reseeding_keeps_changed_sla_and_adds_no_duplicates(): void
    {
        $this->seed(KategoriSeeder::class);
        Kategori::query()->where('nama', 'Kebersihan')->update(['sla_hari' => 3]);

        $this->seed(KategoriSeeder::class);

        $this->assertDatabaseCount('kategori', 11);
        $this->assertDatabaseHas('kategori', ['nama' => 'Kebersihan', 'sla_hari' => 3]);
    }
}
