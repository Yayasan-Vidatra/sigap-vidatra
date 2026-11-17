<?php

namespace Database\Factories;

use App\Enums\Peran;
use App\Enums\TujuanPengalihan;
use App\Models\Laporan;
use App\Models\Pengalihan;
use App\Models\Unit;
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

    /**
     * Pengalihan laporan kekerasan ke Kepala Sekolah sebuah unit (UC-19).
     */
    public function keKepalaSekolah(): static
    {
        return $this->state(fn (array $attributes) => [
            'tujuan' => TujuanPengalihan::KepalaSekolah,
            'unit_tujuan_id' => Unit::factory(),
        ]);
    }
}
