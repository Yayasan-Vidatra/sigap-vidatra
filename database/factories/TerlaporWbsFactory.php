<?php

namespace Database\Factories;

use App\Models\Laporan;
use App\Models\TerlaporWbs;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TerlaporWbs>
 */
class TerlaporWbsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'laporan_id' => Laporan::factory()->wbs(),
            'nama' => fake()->name(),
            'jabatan' => fake()->jobTitle(),
        ];
    }
}
