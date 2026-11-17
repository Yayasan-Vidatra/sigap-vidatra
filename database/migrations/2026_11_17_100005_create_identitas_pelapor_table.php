<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identitas pelapor, 1-1 dengan laporan dan tidak ada bila anonim (Rancangan r1 Bagian 10).
     * Kolom identitas bertipe text karena dienkripsi untuk semua baris.
     */
    public function up(): void
    {
        Schema::create('identitas_pelapor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->unique()->constrained('laporan')->cascadeOnDelete();
            $table->text('nama');
            $table->text('email')->nullable();
            $table->text('no_hp')->nullable();
            $table->text('kontak_orang_tua')->nullable();
            $table->boolean('persetujuan_orang_tua')->default(false);
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identitas_pelapor');
    }
};
