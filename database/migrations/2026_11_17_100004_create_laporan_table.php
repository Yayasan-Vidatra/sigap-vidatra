<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel laporan bersama kedua kanal (Rancangan r1 Bagian 10).
     *
     * judul, kronologi, dan lokasi bertipe text karena dienkripsi bila jenis = wbs.
     */
    public function up(): void
    {
        Schema::create('laporan', function (Blueprint $table) {
            $table->id();
            $table->string('kode_lacak', 20)->unique();
            $table->string('jenis', 20);
            $table->string('sumber', 20)->default('laman');
            $table->foreignId('unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->foreignId('kategori_id')->nullable()->constrained('kategori')->restrictOnDelete();
            $table->text('judul');
            $table->text('kronologi');
            $table->dateTime('waktu_kejadian')->nullable();
            $table->text('lokasi')->nullable();
            $table->string('kedudukan_terlapor', 30)->nullable();
            $table->string('status', 40);
            $table->string('prioritas', 10)->nullable();
            $table->dateTime('batas_waktu')->nullable();
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
