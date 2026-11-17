<?php

namespace Tests\Feature\Models;

use App\Enums\Peran;
use App\Enums\TujuanPengalihan;
use App\Exceptions\PelanggaranIntegritasData;
use App\Models\Laporan;
use App\Models\Pengalihan;
use App\Models\Unit;
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

    public function test_pengalihan_belongs_to_laporan_sender_and_target_unit(): void
    {
        $laporan = Laporan::factory()->create();
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();
        $unit = Unit::factory()->create();

        $pengalihan = Pengalihan::factory()->keKepalaSekolah()->for($laporan)->create([
            'dikirim_oleh' => $admin->id,
            'unit_tujuan_id' => $unit->id,
        ]);

        $this->assertTrue($pengalihan->laporan->is($laporan));
        $this->assertTrue($pengalihan->pengirim->is($admin));
        $this->assertTrue($pengalihan->unitTujuan->is($unit));
    }

    public function test_pengalihan_to_a_kepala_sekolah_who_is_terlapor_is_rejected(): void
    {
        $this->expectException(PelanggaranIntegritasData::class);

        try {
            Pengalihan::factory()->keKepalaSekolah()->create(['kepala_sekolah_terlapor' => true]);
        } finally {
            $this->assertDatabaseCount('pengalihan', 0);
        }
    }

    public function test_pengalihan_to_kepala_sekolah_without_target_unit_is_rejected(): void
    {
        $this->expectException(PelanggaranIntegritasData::class);

        try {
            Pengalihan::factory()->keKepalaSekolah()->create(['unit_tujuan_id' => null]);
        } finally {
            $this->assertDatabaseCount('pengalihan', 0);
        }
    }

    public function test_referral_to_pokja_bsan_is_allowed_when_kepala_sekolah_is_terlapor(): void
    {
        $pengalihan = Pengalihan::factory()->create([
            'tujuan' => TujuanPengalihan::PokjaBsan,
            'kepala_sekolah_terlapor' => true,
        ]);

        $this->assertModelExists($pengalihan);
        $this->assertTrue($pengalihan->fresh()->kepala_sekolah_terlapor);
    }
}
