<?php

namespace Database\Factories;

use App\Enums\StatusLaporan;
use App\Models\Laporan;
use App\Models\RiwayatStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiwayatStatus>
 */
class RiwayatStatusFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'laporan_id' => Laporan::factory(),
            'status_lama' => null,
            'status_baru' => StatusLaporan::Diterima,
            'oleh_user_id' => null,
            'catatan' => null,
            'tampil_ke_pelapor' => false,
        ];
    }
}
