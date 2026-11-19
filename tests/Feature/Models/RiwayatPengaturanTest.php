<?php

namespace Tests\Feature\Models;

use App\Enums\Peran;
use App\Exceptions\PelanggaranIntegritasData;
use App\Filament\Resources\LogAudits\Pages\ListLogAudits;
use App\Models\RiwayatPengaturan;
use App\Models\User;
use App\Services\Pengaturan\PencatatPerubahanPengaturan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RiwayatPengaturanTest extends TestCase
{
    use RefreshDatabase;

    private function riwayat(): RiwayatPengaturan
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->create();
        $log = app(PencatatPerubahanPengaturan::class)->catat($superAdmin, 'units:1', ['kontak_darurat' => '0811'], ['kontak_darurat' => '0822']);

        return $log->riwayatPengaturan()->sole();
    }

    public function test_unchanged_values_write_nothing(): void
    {
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->create();

        $this->assertNull(app(PencatatPerubahanPengaturan::class)->catat($superAdmin, 'units:1', ['aktif' => true, 'nama' => 'SD'], ['aktif' => true, 'nama' => ' SD ']));
        $this->assertDatabaseCount('log_audit', 0);
    }

    public function test_the_model_refuses_update_and_delete(): void
    {
        $riwayat = $this->riwayat();

        try {
            $riwayat->update(['nilai_baru' => 'diubah']);
            $this->fail('Riwayat pengaturan seharusnya tidak bisa diubah.');
        } catch (PelanggaranIntegritasData) {
        }

        $this->expectException(PelanggaranIntegritasData::class);
        $riwayat->delete();
    }

    public function test_raw_update_and_delete_are_rejected_by_the_database(): void
    {
        $riwayat = $this->riwayat();

        foreach (['update' => fn () => DB::table('riwayat_pengaturan')->update(['nilai_baru' => 'x']), 'delete' => fn () => DB::table('riwayat_pengaturan')->delete()] as $operasi => $query) {
            try {
                $query();
                $this->fail("{$operasi} riwayat_pengaturan seharusnya ditolak trigger.");
            } catch (QueryException $e) {
                $this->assertStringContainsString('riwayat_pengaturan hanya bisa ditambah', $e->getMessage());
            }
        }

        $this->assertDatabaseHas('riwayat_pengaturan', ['id' => $riwayat->id, 'nilai_baru' => '0822']);
    }

    public function test_super_admin_reads_old_and_new_values_in_the_audit_log(): void
    {
        $riwayat = $this->riwayat();
        $superAdmin = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();

        $this->actingAs($superAdmin);

        Livewire::test(ListLogAudits::class)
            ->assertCanSeeTableRecords([$riwayat->logAudit])
            ->assertSee('kontak_darurat: 0811 → 0822');
    }
}
