<?php

namespace Tests\Feature\Models;

use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Models\Laporan;
use App\Models\RiwayatStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RiwayatStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_catatan_is_stored_encrypted(): void
    {
        $riwayat = RiwayatStatus::factory()->create([
            'status_lama' => StatusLaporan::Diterima,
            'status_baru' => StatusLaporan::Ditolak,
            'catatan' => 'Ditolak karena pelapor Pak Hendra tidak bisa dihubungi.',
        ]);

        $mentah = DB::table('riwayat_status')->where('id', $riwayat->id)->value('catatan');

        $this->assertStringNotContainsString('Hendra', $mentah);
        $this->assertSame('Ditolak karena pelapor Pak Hendra tidak bisa dihubungi.', Crypt::decryptString($mentah));
    }

    public function test_catatan_is_hidden_from_the_reporter_unless_marked(): void
    {
        $laporan = Laporan::factory()->create();

        $riwayat = $laporan->riwayatStatus()->create([
            'status_baru' => StatusLaporan::Diterima,
            'catatan' => 'Catatan internal petugas.',
        ]);

        $this->assertFalse($riwayat->fresh()->tampil_ke_pelapor);
    }

    public function test_riwayat_belongs_to_laporan_and_officer(): void
    {
        $laporan = Laporan::factory()->create();
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();

        $riwayat = RiwayatStatus::factory()->for($laporan)->create([
            'status_baru' => StatusLaporan::Terverifikasi,
            'oleh_user_id' => $admin->id,
        ]);

        $this->assertTrue($riwayat->laporan->is($laporan));
        $this->assertTrue($riwayat->oleh->is($admin));
        $this->assertSame(StatusLaporan::Terverifikasi, $riwayat->fresh()->status_baru);
    }
}
