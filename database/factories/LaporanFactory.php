<?php

namespace Database\Factories;

use App\Enums\JenisLaporan;
use App\Enums\JenisPelapor;
use App\Enums\KedudukanTerlapor;
use App\Enums\StatusLaporan;
use App\Enums\SumberLaporan;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Bawaan: pengaduan dari laman berstatus Diterima. Pakai state wbs() untuk laporan WBS.
 * Kategori bawaan dibuat mengikuti jenis, sehingga for($kategori) tidak tertimpa state wbs().
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
            'kategori_id' => fn (array $attributes) => Kategori::factory()->state(['jenis' => $attributes['jenis']]),
            'judul' => fake()->sentence(),
            'kronologi' => fake()->paragraph(),
            'waktu_kejadian' => fake()->dateTimeBetween('-1 month'),
            'lokasi' => fake()->streetName(),
            'jenis_pelapor' => JenisPelapor::OrangTua,
            'jabatan_pihak_dikeluhkan' => 'Wali kelas',
            'status' => StatusLaporan::Diterima,
        ];
    }

    public function wbs(): static
    {
        return $this->state(fn (array $attributes) => [
            'kode_lacak' => 'WBS-'.strtoupper(fake()->unique()->bothify('?#?#-#?#?')),
            'jenis' => JenisLaporan::Wbs,
            'jenis_pelapor' => null,
            'jabatan_pihak_dikeluhkan' => null,
            'saksi' => fake()->name(),
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
