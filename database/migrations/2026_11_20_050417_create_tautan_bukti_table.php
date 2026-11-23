<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisi 20-11 (bukti besar): tautan Google Drive dari pelapor, pada laporan atau pesan lanjutan di Lacak. url
     * bertipe text karena dienkripsi untuk semua baris, pengaduan maupun WBS.
     */
    public function up(): void
    {
        Schema::create('tautan_bukti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan')->cascadeOnDelete();
            $table->foreignId('pesan_id')->nullable()->constrained('pesan')->cascadeOnDelete();
            $table->text('url');
            $table->timestamp('dibuat_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tautan_bukti');
    }
};
