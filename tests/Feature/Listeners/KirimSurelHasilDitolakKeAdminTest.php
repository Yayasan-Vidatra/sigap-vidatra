<?php

namespace Tests\Feature\Listeners;

use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Mail\HasilPengaduanDitolakPelapor;
use App\Models\Laporan;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengaduan\KonfirmasiPelapor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KirimSurelHasilDitolakKeAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_admin_and_school_admin_of_the_unit_are_emailed_when_the_reporter_rejects_the_result(): void
    {
        Mail::fake();
        $unit = Unit::factory()->create();
        $laporan = Laporan::factory()->for($unit)->berstatus(StatusLaporan::MenungguKonfirmasi)->create();
        $adminYayasan = User::factory()->peran(Peran::AdminPengaduan)->create();
        $adminSekolah = User::factory()->peran(Peran::AdminPengaduan)->for($unit)->create();
        $adminSekolahLain = User::factory()->peran(Peran::AdminPengaduan)->for(Unit::factory())->create();
        $adminTerkunci = User::factory()->peran(Peran::AdminPengaduan)->terkunci()->create();

        app(KonfirmasiPelapor::class)->tolakHasil($laporan, 'Keran masih bocor.');

        foreach ([$adminYayasan, $adminSekolah] as $admin) {
            Mail::assertQueued(HasilPengaduanDitolakPelapor::class, fn (HasilPengaduanDitolakPelapor $surel): bool => $surel->hasTo($admin->email));
        }

        foreach ([$adminSekolahLain, $adminTerkunci] as $bukanPenerima) {
            Mail::assertNotQueued(HasilPengaduanDitolakPelapor::class, fn (HasilPengaduanDitolakPelapor $surel): bool => $surel->hasTo($bukanPenerima->email));
        }

        Mail::assertQueued(HasilPengaduanDitolakPelapor::class, function (HasilPengaduanDitolakPelapor $surel): bool {
            $surel->assertSeeInText('Keran masih bocor.');

            return true;
        });
    }
}
