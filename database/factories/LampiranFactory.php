<?php

namespace Database\Factories;

use App\Enums\StatusPindai;
use App\Models\Lampiran;
use App\Models\Laporan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lampiran>
 */
class LampiranFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'laporan_id' => Laporan::factory(),
            'pesan_id' => null,
            'nama_asli' => fake()->word().'.pdf',
            'path_acak' => 'lampiran/'.Str::random(40),
            'mime' => 'application/pdf',
            'ukuran' => fake()->numberBetween(1_000, 10_000_000),
            'hash_sha256' => hash('sha256', Str::random(32)),
            'status_pindai' => StatusPindai::Menunggu,
        ];
    }
}
