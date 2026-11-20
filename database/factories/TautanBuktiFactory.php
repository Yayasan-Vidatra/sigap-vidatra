<?php

namespace Database\Factories;

use App\Models\Laporan;
use App\Models\TautanBukti;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TautanBukti>
 */
class TautanBuktiFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'laporan_id' => Laporan::factory(),
            'pesan_id' => null,
            'url' => 'https://drive.google.com/file/d/'.fake()->regexify('[A-Za-z0-9_-]{28}').'/view',
        ];
    }
}
