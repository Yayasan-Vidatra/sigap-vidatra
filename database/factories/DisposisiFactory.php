<?php

namespace Database\Factories;

use App\Enums\Peran;
use App\Models\Disposisi;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disposisi>
 */
class DisposisiFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'laporan_id' => Laporan::factory(),
            'unit_id' => Unit::factory(),
            'pic_user_id' => User::factory()->peran(Peran::PicUnit),
            'instruksi' => fake()->sentence(),
            'tindak_lanjut' => null,
            'selesai_pada' => null,
        ];
    }
}
