<?php

namespace Database\Factories;

use App\Enums\JenisAkun;
use App\Enums\Peran;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'peran' => null,
            'unit_id' => null,
            'jenis_akun' => JenisAkun::Lokal,
        ];
    }

    /**
     * Pengguna dengan peran petugas tertentu. Peran yang wajib terikat unit mendapat unit baru bila
     * belum diberi lewat for($unit).
     */
    public function peran(Peran $peran): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => $peran,
            'unit_id' => $attributes['unit_id'] ?? ($peran->wajibUnit() ? Unit::factory() : null),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
