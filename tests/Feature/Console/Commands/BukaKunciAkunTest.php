<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BukaKunciAkunTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlocks_a_locked_account_and_logs_it(): void
    {
        $user = User::factory()->peran(Peran::KetuaTimWbs)->terkunci()->create();

        $this->artisan('sigap:buka-kunci-akun', ['email' => $user->email])
            ->expectsOutput('Kunci akun sudah dibuka.')
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->terkunci());
        $this->assertDatabaseHas('log_audit', [
            'user_id' => null,
            'aksi' => AksiAudit::KunciAkunDibuka->value,
            'objek' => "users:{$user->id}",
        ]);
    }

    public function test_fails_for_an_unknown_email(): void
    {
        $this->artisan('sigap:buka-kunci-akun', ['email' => 'tidak.ada@example.com'])
            ->expectsOutput('Akun dengan surel itu tidak ditemukan.')
            ->assertFailed();

        $this->assertDatabaseCount('log_audit', 0);
    }
}
