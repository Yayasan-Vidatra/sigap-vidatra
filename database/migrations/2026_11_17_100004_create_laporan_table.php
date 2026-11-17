<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel laporan bersama kedua kanal (Rancangan r1 Bagian 10).
     *
     * judul, kronologi, lokasi, dan saksi bertipe text karena dienkripsi bila jenis = wbs.
     * jenis_pelapor, jabatan_pihak_dikeluhkan, dan penilaian hanya untuk pengaduan; kesimpulan hanya
     * untuk WBS (pelengkap Bagian 10, lihat prd/log-deviasi.md).
     */
    public function up(): void
    {
        Schema::create('laporan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_lacak', 20)->unique();
            $table->string('jenis', 20);
            $table->string('sumber', 20)->default('laman');
            $table->string('jenis_pelapor', 20)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('kategori_id')->nullable()->constrained('kategori')->restrictOnDelete();
            $table->text('judul');
            $table->text('kronologi');
            $table->dateTime('waktu_kejadian')->nullable();
            $table->text('lokasi')->nullable();
            $table->string('jabatan_pihak_dikeluhkan', 150)->nullable();
            $table->text('saksi')->nullable();
            $table->string('kedudukan_terlapor', 30)->nullable();
            $table->string('status', 40);
            $table->string('prioritas', 10)->nullable();
            $table->dateTime('batas_waktu')->nullable();
            $table->unsignedTinyInteger('penilaian')->nullable();
            $table->string('kesimpulan', 30)->nullable();
            $table->string('dialihkan_ke', 30)->nullable();
            $table->foreignId('perpanjangan_disetujui_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('pin_hash')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();

            $table->index(['jenis', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan');
    }
};
