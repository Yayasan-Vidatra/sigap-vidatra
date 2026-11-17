<?php

namespace Database\Factories;

use App\Enums\JenisLaporan;
use App\Models\Kategori;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kategori>
 */
class KategoriFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'jenis' => JenisLaporan::Pengaduan,
            'nama' => ucfirst(fake()->unique()->words(3, true)),
            'sla_hari' => 14,
        ];
    }

    public function wbs(): static
    {
        return $this->state(fn (array $attributes) => [
            'jenis' => JenisLaporan::Wbs,
            'sla_hari' => 30,
        ]);
    }
}
