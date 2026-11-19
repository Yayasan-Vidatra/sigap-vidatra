<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PESAN_TOLAK = 'riwayat_pengaturan hanya bisa ditambah';

    /**
     * Nilai lama dan baru setiap perubahan Pengaturan (H-19), terikat pada satu baris log audit
     * `mengubah_pengaturan`. log_audit sendiri tetap minimal (siapa, aksi, objek, waktu). Nilai rahasia
     * (kata sandi) tidak pernah disimpan. Seperti log_audit, trigger menolak UPDATE dan DELETE.
     * Usulan pelengkap Bagian 10, lihat prd/log-deviasi.md.
     */
    public function up(): void
    {
        Schema::create('riwayat_pengaturan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_audit_id')->constrained('log_audit')->restrictOnDelete();
            $table->string('kolom', 100);
            $table->text('nilai_lama')->nullable();
            $table->text('nilai_baru')->nullable();
        });

        DB::unprepared($this->sqlTrigger('riwayat_pengaturan_tolak_update', 'UPDATE'));
        DB::unprepared($this->sqlTrigger('riwayat_pengaturan_tolak_delete', 'DELETE'));
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS riwayat_pengaturan_tolak_update');
        DB::unprepared('DROP TRIGGER IF EXISTS riwayat_pengaturan_tolak_delete');
        Schema::dropIfExists('riwayat_pengaturan');
    }

    private function sqlTrigger(string $nama, string $operasi): string
    {
        $pesan = self::PESAN_TOLAK;

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "CREATE TRIGGER {$nama} BEFORE {$operasi} ON riwayat_pengaturan FOR EACH ROW "
                ."SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$pesan}'",
            'sqlite' => "CREATE TRIGGER {$nama} BEFORE {$operasi} ON riwayat_pengaturan "
                ."BEGIN SELECT RAISE(ABORT, '{$pesan}'); END",
            default => throw new RuntimeException('Trigger riwayat_pengaturan belum didukung untuk driver ini.'),
        };
    }
};
