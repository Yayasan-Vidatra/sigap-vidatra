<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PESAN_TOLAK = 'log_audit hanya bisa ditambah';

    /**
     * Log audit yang hanya bisa ditambah (Rancangan r1 Bagian 10, NF-07, aturan proyek no. 6).
     *
     * objek berformat "{tabel}:{id}", misalnya "laporan:12". Trigger basis data menolak UPDATE dan
     * DELETE, termasuk yang dijalankan di luar Eloquent.
     */
    public function up(): void
    {
        Schema::create('log_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('aksi', 100);
            $table->string('objek', 150)->index();
            $table->timestamp('waktu')->nullable()->index();
        });

        DB::unprepared($this->sqlTrigger('log_audit_tolak_update', 'UPDATE'));
        DB::unprepared($this->sqlTrigger('log_audit_tolak_delete', 'DELETE'));
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS log_audit_tolak_update');
        DB::unprepared('DROP TRIGGER IF EXISTS log_audit_tolak_delete');
        Schema::dropIfExists('log_audit');
    }

    private function sqlTrigger(string $nama, string $operasi): string
    {
        $pesan = self::PESAN_TOLAK;

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "CREATE TRIGGER {$nama} BEFORE {$operasi} ON log_audit FOR EACH ROW "
                ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$pesan}'",
            'sqlite' => "CREATE TRIGGER {$nama} BEFORE {$operasi} ON log_audit "
                ."BEGIN SELECT RAISE(ABORT, '{$pesan}'); END",
            default => throw new RuntimeException('Trigger log_audit belum didukung untuk driver ini.'),
        };
    }
};
