<?php

namespace Tests\Feature\Models;

use App\Exceptions\PelanggaranIntegritasData;
use App\Models\Laporan;
use App\Models\TerlaporWbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TerlaporWbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nama_and_jabatan_are_stored_encrypted(): void
    {
        $terlapor = TerlaporWbs::factory()->create([
            'nama' => 'Budi Santoso',
            'jabatan' => 'Bendahara',
        ]);

        $mentah = DB::table('terlapor_wbs')->where('id', $terlapor->id)->first();

        $this->assertSame('Budi Santoso', Crypt::decryptString($mentah->nama));
        $this->assertSame('Bendahara', Crypt::decryptString($mentah->jabatan));
        $this->assertStringNotContainsString('Budi', $mentah->nama);
    }

    public function test_terlapor_on_a_pengaduan_is_rejected(): void
    {
        $pengaduan = Laporan::factory()->create();

        $this->expectException(PelanggaranIntegritasData::class);

        try {
            TerlaporWbs::factory()->for($pengaduan)->create();
        } finally {
            $this->assertDatabaseCount('terlapor_wbs', 0);
        }
    }

    public function test_terlapor_belongs_to_its_laporan(): void
    {
        $laporan = Laporan::factory()->wbs()->create();
        $terlapor = TerlaporWbs::factory()->for($laporan)->create();

        $this->assertTrue($terlapor->laporan->is($laporan));
    }
}
