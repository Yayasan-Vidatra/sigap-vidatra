<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Metadata lampiran; berkasnya di disk privat dengan nama acak (Rancangan r1 Bagian 8 dan 10).
     */
    public function up(): void
    {
        Schema::create('lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan')->cascadeOnDelete();
            $table->foreignId('pesan_id')->nullable()->constrained('pesan')->cascadeOnDelete();
            $table->string('nama_asli');
            $table->string('path_acak')->unique();
            $table->string('mime', 127);
            $table->unsignedBigInteger('ukuran');
            $table->char('hash_sha256', 64);
            $table->string('status_pindai', 20)->default('menunggu');
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran');
    }
};
