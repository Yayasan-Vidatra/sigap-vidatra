<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengalihan laporan ke Kepala Sekolah, Komite Etik, atau Pokja BSAN (Rancangan r1 Bagian 10, UC-19).
     * dibuat_pada adalah waktu kirim. unit_tujuan_id dan kepala_sekolah_terlapor adalah pelengkap
     * Bagian 10 untuk US-19/US-20 (lihat prd/log-deviasi.md).
     */
    public function up(): void
    {
        Schema::create('pengalihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan')->cascadeOnDelete();
            $table->string('tujuan', 30);
            $table->foreignId('unit_tujuan_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->boolean('kepala_sekolah_terlapor')->default(false);
            $table->text('ringkasan');
            $table->foreignId('dikirim_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamp('diterima_pada')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengalihan');
    }
};
