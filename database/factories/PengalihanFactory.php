<?php

namespace Database\Factories;

use App\Enums\Peran;
use App\Enums\TujuanPengalihan;
use App\Models\Laporan;
use App\Models\Pengalihan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pengalihan>
 */
class PengalihanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'laporan_id' => Laporan::factory(),
            'tujuan' => TujuanPengalihan::KomiteEtik,
            'ringkasan' => fake()->paragraph(),
            'dikirim_oleh' => User::factory()->peran(Peran::AdminPengaduan),
            'diterima_pada' => null,
        ];
    }
}
