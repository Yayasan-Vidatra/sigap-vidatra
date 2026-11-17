<?php

namespace Database\Factories;

use App\Enums\JenisLaporan;
use App\Enums\KedudukanTerlapor;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bawaan: pengaduan dari laman berstatus Diterima. Pakai state wbs() untuk laporan WBS.
 *
 * Kode lacak di sini hanya data contoh; pembuatan kode lacak yang sebenarnya dikerjakan di US-07.
 *
 * @extends Factory<Laporan>
 */
class LaporanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_lacak' => 'PGD-'.now()->format('ym').'-'.fake()->unique()->numerify('####'),
            'jenis' => JenisLaporan::Pengaduan,
            'sumber' => SumberLaporan::Laman,
            'unit_id' => Unit::factory(),
            'kategori_id' => Kategori::factory(),
            'judul' => fake()->sentence(),
            'kronologi' => fake()->paragraph(),
            'waktu_kejadian' => fake()->dateTimeBetween('-1 month'),
            'lokasi' => fake()->streetName(),
            'status' => StatusLaporan::Diterima,
        ];
    }

    public function wbs(): static
    {
        return $this->state(fn (array $attributes) => [
            'kode_lacak' => 'WBS-'.strtoupper(fake()->unique()->bothify('?#?#-#?#?')),
            'jenis' => JenisLaporan::Wbs,
            'kategori_id' => Kategori::factory()->wbs(),
            'kedudukan_terlapor' => KedudukanTerlapor::GuruPegawai,
            'pin_hash' => fake()->numerify('######'),
        ]);
    }

    public function berstatus(StatusLaporan $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
