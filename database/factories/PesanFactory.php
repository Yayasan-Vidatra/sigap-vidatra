<?php

namespace Database\Factories;

use App\Enums\PengirimPesan;
use App\Models\Laporan;
use App\Models\Pesan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pesan>
 */
class PesanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'laporan_id' => Laporan::factory(),
            'pengirim' => PengirimPesan::Pelapor,
            'isi' => fake()->sentence(),
            'internal' => false,
        ];
    }

    /**
     * Catatan internal petugas yang tidak terlihat pelapor.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'pengirim' => PengirimPesan::Petugas,
            'internal' => true,
        ]);
    }
}
