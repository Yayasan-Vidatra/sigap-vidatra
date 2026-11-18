<?php

namespace Tests\Feature\Listeners;

use App\Enums\Peran;
use App\Enums\Prioritas;
use App\Enums\StatusLaporan;
use App\Mail\DisposisiPengaduan;
use App\Models\Kategori;
use App\Models\Laporan;
use App\Models\User;
use App\Services\Pengaduan\PenangananPengaduan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KirimSurelDisposisiKePicTest extends TestCase
{
    use RefreshDatabase;

    public function test_pic_receives_a_queued_email_with_deadline_in_wita_and_a_panel_link_but_no_chronology(): void
    {
        Mail::fake();
        $kategori = Kategori::factory()->create(['nama' => 'Sarana', 'sla_hari' => 14]);
        $laporan = Laporan::factory()->berstatus(StatusLaporan::Terverifikasi)->for($kategori)->create([
            'kode_lacak' => 'PGD-2611-ABC234',
            'judul' => 'AC kelas 7B mati',
            'kronologi' => 'Rahasia kronologi yang tidak boleh ada di surel.',
            'dibuat_pada' => CarbonImmutable::parse('2026-11-17 23:46:00', 'UTC'),
        ]);
        $pic = User::factory()->peran(Peran::PicUnit)->create(['unit_id' => $laporan->unit_id, 'email' => 'pic.smp@example.com']);
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();

        app(PenangananPengaduan::class)->disposisi($laporan, $admin, $laporan->unit, $pic, Prioritas::Tinggi, 'Mohon segera.');

        Mail::assertQueued(DisposisiPengaduan::class, function (DisposisiPengaduan $surel) use ($laporan): bool {
            $surel->assertSeeInText('PGD-2611-ABC234');
            $surel->assertSeeInText('AC kelas 7B mati');
            $surel->assertSeeInText('Selasa, 08 Desember 2026');
            $surel->assertSeeInText('Mohon segera.');
            $surel->assertSeeInHtml("/panel/pengaduan-unit/{$laporan->id}");
            $surel->assertDontSeeInText('Rahasia kronologi');

            return $surel->hasTo('pic.smp@example.com');
        });
    }
}
