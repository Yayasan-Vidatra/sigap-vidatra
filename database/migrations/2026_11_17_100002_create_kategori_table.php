<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori pengaduan dan jenis pelanggaran WBS, dibedakan lewat kolom jenis (Rancangan r1 Bagian 10).
     */
    public function up(): void
    {
        Schema::create('kategori', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 20);
            $table->string('nama', 150);
            $table->unsignedSmallInteger('sla_hari');
            $table->timestamp('dibuat_pada')->nullable();
            $table->timestamp('diperbarui_pada')->nullable();

            $table->unique(['jenis', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori');
    }
};
