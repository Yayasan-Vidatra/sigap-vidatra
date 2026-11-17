<?php

namespace Database\Factories;

use App\Models\LogAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogAudit>
 */
class LogAuditFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'aksi' => 'membuka_laporan',
            'objek' => 'laporan:'.fake()->numberBetween(1, 1000),
        ];
    }
}
