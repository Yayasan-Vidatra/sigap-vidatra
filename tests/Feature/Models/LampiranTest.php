<?php

namespace Tests\Feature\Models;

use App\Models\Lampiran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LampiranTest extends TestCase
{
    use RefreshDatabase;

    public function test_nama_asli_is_stored_encrypted(): void
    {
        $lampiran = Lampiran::factory()->create(['nama_asli' => 'rekaman_budi_santoso.m4a']);

        $mentah = DB::table('lampiran')->where('id', $lampiran->id)->value('nama_asli');

        $this->assertStringNotContainsString('budi', $mentah);
        $this->assertSame('rekaman_budi_santoso.m4a', Crypt::decryptString($mentah));
        $this->assertSame('rekaman_budi_santoso.m4a', $lampiran->fresh()->nama_asli);
    }
}
