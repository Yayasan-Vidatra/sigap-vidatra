<?php

namespace Tests\Feature\Models;

use App\Models\IdentitasPelapor;
use App\Models\Laporan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IdentitasPelaporTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{bool}>
     */
    public static function jenisLaporan(): array
    {
        return [
            'pengaduan' => [false],
            'wbs' => [true],
        ];
    }

    #[DataProvider('jenisLaporan')]
    public function test_identity_columns_are_stored_encrypted_on_every_kanal(bool $wbs): void
    {
        $laporan = $wbs ? Laporan::factory()->wbs()->create() : Laporan::factory()->create();
        $identitas = IdentitasPelapor::factory()->for($laporan)->create([
            'nama' => 'Rina Kartika',
            'email' => 'rina@example.com',
            'no_hp' => '081234567890',
            'kontak_orang_tua' => '089876543210',
        ]);

        $mentah = DB::table('identitas_pelapor')->where('id', $identitas->id)->first();

        $this->assertSame('Rina Kartika', Crypt::decryptString($mentah->nama));
        $this->assertSame('rina@example.com', Crypt::decryptString($mentah->email));
        $this->assertSame('081234567890', Crypt::decryptString($mentah->no_hp));
        $this->assertSame('089876543210', Crypt::decryptString($mentah->kontak_orang_tua));
        $this->assertStringNotContainsString('Rina', $mentah->nama);
    }

    public function test_identitas_belongs_to_its_laporan(): void
    {
        $laporan = Laporan::factory()->create();
        $identitas = IdentitasPelapor::factory()->siswa()->for($laporan)->create();

        $this->assertTrue($identitas->laporan->is($laporan));
        $this->assertTrue($identitas->persetujuan_orang_tua);
    }
}
