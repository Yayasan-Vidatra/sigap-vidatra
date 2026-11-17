<?php

namespace Tests\Feature\Mail;

use App\Mail\KonfirmasiPengaduan;
use App\Models\IdentitasPelapor;
use App\Models\Laporan;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KonfirmasiPengaduanTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_shows_the_tracking_code_and_how_to_track(): void
    {
        $laporan = Laporan::factory()->for(Unit::factory()->state(['nama' => 'SMP YPVDP']))->create([
            'kode_lacak' => 'PGD-2611-7KQ2M9',
            'judul' => 'Toilet lantai 2 rusak',
        ]);
        IdentitasPelapor::factory()->for($laporan)->create(['nama' => 'Rina Kartika']);

        $surel = new KonfirmasiPengaduan($laporan->fresh());

        $surel->assertHasSubject('Pengaduan Anda sudah diterima (PGD-2611-7KQ2M9)');
        $surel->assertSeeInHtml('PGD-2611-7KQ2M9');
        $surel->assertSeeInHtml('Rina Kartika');
        $surel->assertSeeInHtml('SMP YPVDP');
        $surel->assertSeeInHtml(route('lacak'));
        $surel->assertSeeInText('2 hari kerja');
    }

    public function test_sends_to_the_reporter_email(): void
    {
        $laporan = Laporan::factory()->create();
        IdentitasPelapor::factory()->for($laporan)->create(['email' => 'rina@example.com']);

        $this->assertSame('rina@example.com', KonfirmasiPengaduan::alamatTujuan($laporan->fresh()));
    }

    public function test_student_complaint_goes_to_the_guardian_email(): void
    {
        $laporan = Laporan::factory()->create();
        IdentitasPelapor::factory()->siswa()->for($laporan)->create(['kontak_orang_tua' => 'wali@example.com']);

        $this->assertSame('wali@example.com', KonfirmasiPengaduan::alamatTujuan($laporan->fresh()));
    }

    public function test_no_email_is_sent_when_only_a_phone_number_was_given(): void
    {
        $laporan = Laporan::factory()->create();
        IdentitasPelapor::factory()->for($laporan)->create(['email' => null, 'no_hp' => '081234567890']);

        $this->assertNull(KonfirmasiPengaduan::alamatTujuan($laporan->fresh()));
    }
}
