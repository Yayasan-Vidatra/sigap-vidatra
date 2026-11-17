<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesan dua arah dan catatan internal (internal = true), terenkripsi (Rancangan r1 Bagian 10).
     */
    public function up(): void
    {
        Schema::create('pesan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan')->cascadeOnDelete();
            $table->string('pengirim', 20);
            $table->text('isi');
            $table->boolean('internal')->default(false);
            $table->timestamp('dibuat_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesan');
    }
};
