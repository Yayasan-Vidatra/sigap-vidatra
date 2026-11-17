<?php

namespace Database\Factories;

use App\Models\IdentitasPelapor;
use App\Models\Laporan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdentitasPelapor>
 */
class IdentitasPelaporFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'laporan_id' => Laporan::factory(),
            'nama' => fake()->name(),
            'email' => fake()->safeEmail(),
            'no_hp' => fake()->numerify('08##########'),
            'kontak_orang_tua' => null,
            'persetujuan_orang_tua' => false,
        ];
    }

    /**
     * Pelapor siswa: kontak diisi kontak orang tua/wali beserta persetujuannya (UC-01 1a).
     */
    public function siswa(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'no_hp' => null,
            'kontak_orang_tua' => fake()->numerify('08##########'),
            'persetujuan_orang_tua' => true,
        ]);
    }
}
