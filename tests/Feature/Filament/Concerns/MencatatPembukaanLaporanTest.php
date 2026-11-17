<?php

namespace Tests\Feature\Filament\Concerns;

use App\Enums\AksiAudit;
use App\Enums\Peran;
use App\Models\Laporan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\HalamanLaporanUji;
use Tests\TestCase;

class MencatatPembukaanLaporanTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_report_page_is_logged_with_the_officer_and_report(): void
    {
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->create();
        $laporan = Laporan::factory()->wbs()->create();

        $this->actingAs($ketua);
        Livewire::test(HalamanLaporanUji::class, ['record' => $laporan]);

        $this->assertDatabaseHas('log_audit', [
            'user_id' => $ketua->id,
            'aksi' => AksiAudit::MembukaLaporan->value,
            'objek' => "laporan:{$laporan->id}",
        ]);
    }

    public function test_later_interactions_on_the_same_page_are_not_logged_again(): void
    {
        $admin = User::factory()->peran(Peran::AdminPengaduan)->create();
        $laporan = Laporan::factory()->create();

        $this->actingAs($admin);
        Livewire::test(HalamanLaporanUji::class, ['record' => $laporan])
            ->call('$refresh')
            ->call('$refresh');

        $this->assertDatabaseCount('log_audit', 1);
    }
}
