<?php

namespace Tests\Feature\Models;

use App\Enums\Peran;
use App\Models\Laporan;
use App\Models\Pengalihan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PengalihanTest extends TestCase
{
    use RefreshDatabase;

    public function test_ringkasan_is_stored_encrypted(): void
    {
        $pengalihan = Pengalihan::factory()->create(['ringkasan' => 'Dugaan perundungan di kelas 8.']);

        $mentah = DB::table('pengalihan')->where('id', $pengalihan->id)->value('ringkasan');

        $this->assertNotSame('Dugaan perundungan di kelas 8.', $mentah);
        $this->assertSame('Dugaan perundungan di kelas 8.', Crypt::decryptString($mentah));
    }

    public function test_pengalihan_belongs_to_laporan_and_sender(): void
    {
        $laporan = Laporan::factory()->create();
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();
        $pengalihan = Pengalihan::factory()->for($laporan)->create(['dikirim_oleh' => $admin->id]);

        $this->assertTrue($pengalihan->laporan->is($laporan));
        $this->assertTrue($pengalihan->pengirim->is($admin));
    }
}
