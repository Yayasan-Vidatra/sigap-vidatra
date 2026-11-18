<?php

namespace Tests\Feature\Services\Sesi;

use App\Services\Sesi\PenanganSesiTanpaJejak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PenanganSesiTanpaJejakTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_session_driver_uses_the_handler_without_ip_or_user_agent(): void
    {
        config(['session.driver' => 'database']);

        $this->assertInstanceOf(PenanganSesiTanpaJejak::class, app('session')->driver('database')->getHandler());
    }

    public function test_session_rows_never_store_ip_address_or_user_agent(): void
    {
        config(['session.driver' => 'database']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->withHeader('User-Agent', 'AgenUji/9.9')
            ->get('/')
            ->assertOk();

        $baris = DB::table('sessions')->get();

        $this->assertCount(1, $baris);
        $this->assertNull($baris[0]->ip_address);
        $this->assertNull($baris[0]->user_agent);
        $this->assertStringNotContainsString('203.0.113.77', $baris[0]->payload);
        $this->assertStringNotContainsString('AgenUji', base64_decode($baris[0]->payload));
    }
}
