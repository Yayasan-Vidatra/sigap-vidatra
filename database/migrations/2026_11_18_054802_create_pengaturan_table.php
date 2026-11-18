<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan teks halaman publik dan berkas jaminan kerahasiaan (aturan proyek no. 7; pelengkap Bagian 10
     * yang disetujui untuk US-25, lihat prd/log-deviasi.md). Dibuat lebih awal pada Hari 9 karena H-03 membacanya;
     * pengelolaannya lewat H-19 menyusul Hari 13.
     */
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 100)->unique();
            $table->text('nilai')->nullable();
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
