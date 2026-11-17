<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat perpindahan status laporan (Rancangan r1 Bagian 10). catatan dienkripsi untuk semua baris.
     */
    public function up(): void
    {
        Schema::create('riwayat_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan')->cascadeOnDelete();
            $table->string('status_lama', 40)->nullable();
            $table->string('status_baru', 40);
            $table->foreignId('oleh_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('waktu')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_status');
    }
};
