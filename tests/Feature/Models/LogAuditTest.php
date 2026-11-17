<?php

namespace Tests\Feature\Models;

use App\Enums\Peran;
use App\Exceptions\PelanggaranIntegritasData;
use App\Models\Laporan;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LogAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_catat_stores_user_action_and_object_reference(): void
    {
        $this->freezeTime();
        $ketua = User::factory()->peran(Peran::KetuaTimWbs)->create();
        $laporan = Laporan::factory()->wbs()->create();

        $log = LogAudit::catat($ketua, 'membuka_identitas_pelapor', $laporan);

        $this->assertDatabaseHas('log_audit', [
            'id' => $log->id,
            'user_id' => $ketua->id,
            'aksi' => 'membuka_identitas_pelapor',
            'objek' => "laporan:{$laporan->id}",
            'waktu' => now()->toDateTimeString(),
        ]);
        $this->assertTrue($log->user->is($ketua));
        $this->assertSame([$log->id], $ketua->logAudit->modelKeys());
    }

    public function test_updating_a_log_through_the_model_is_rejected(): void
    {
        $log = LogAudit::factory()->create(['aksi' => 'membuka_laporan']);

        try {
            $log->update(['aksi' => 'diubah']);
            $this->fail('Perubahan log audit seharusnya ditolak.');
        } catch (PelanggaranIntegritasData) {
        }

        $this->assertDatabaseHas('log_audit', ['id' => $log->id, 'aksi' => 'membuka_laporan']);
    }

    public function test_deleting_a_log_through_the_model_is_rejected(): void
    {
        $log = LogAudit::factory()->create();

        try {
            $log->delete();
            $this->fail('Penghapusan log audit seharusnya ditolak.');
        } catch (PelanggaranIntegritasData) {
        }

        $this->assertModelExists($log);
    }

    public function test_updating_logs_with_a_raw_query_is_rejected_by_the_database(): void
    {
        $log = LogAudit::factory()->create(['aksi' => 'membuka_laporan']);

        try {
            DB::table('log_audit')->update(['aksi' => 'diubah']);
            $this->fail('UPDATE log_audit seharusnya ditolak trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('log_audit hanya bisa ditambah', $e->getMessage());
        }

        $this->assertDatabaseHas('log_audit', ['id' => $log->id, 'aksi' => 'membuka_laporan']);
    }

    public function test_deleting_logs_with_a_raw_query_is_rejected_by_the_database(): void
    {
        $log = LogAudit::factory()->create();

        try {
            DB::table('log_audit')->delete();
            $this->fail('DELETE log_audit seharusnya ditolak trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('log_audit hanya bisa ditambah', $e->getMessage());
        }

        $this->assertModelExists($log);
    }
}
