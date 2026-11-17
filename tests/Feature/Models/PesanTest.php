<?php

namespace Tests\Feature\Models;

use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Pesan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PesanTest extends TestCase
{
    use RefreshDatabase;

    public function test_wbs_internal_note_is_stored_encrypted(): void
    {
        $catatan = Pesan::factory()->internal()->for(Laporan::factory()->wbs())->create([
            'isi' => 'Saksi kedua mengonfirmasi transfer.',
        ]);

        $mentah = DB::table('pesan')->where('id', $catatan->id)->value('isi');

        $this->assertNotSame('Saksi kedua mengonfirmasi transfer.', $mentah);
        $this->assertSame('Saksi kedua mengonfirmasi transfer.', Crypt::decryptString($mentah));
        $this->assertTrue($catatan->fresh()->internal);
    }

    public function test_pengaduan_message_is_stored_encrypted(): void
    {
        $pesan = Pesan::factory()->for(Laporan::factory())->create(['isi' => 'Kapan keran diperbaiki?']);

        $mentah = DB::table('pesan')->where('id', $pesan->id)->value('isi');

        $this->assertSame('Kapan keran diperbaiki?', Crypt::decryptString($mentah));
    }

    public function test_pesan_belongs_to_laporan_and_has_its_lampiran(): void
    {
        $laporan = Laporan::factory()->create();
        $pesan = Pesan::factory()->for($laporan)->create();
        $lampiran = Lampiran::factory()->for($laporan)->for($pesan)->create();

        $this->assertTrue($pesan->laporan->is($laporan));
        $this->assertSame([$lampiran->id], $pesan->lampiran->modelKeys());
        $this->assertTrue($lampiran->pesan->is($pesan));
    }
}
