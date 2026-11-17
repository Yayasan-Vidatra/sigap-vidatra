<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menandai catatan riwayat status yang boleh dibaca pelapor di halaman Lacak (H-07), misalnya alasan
     * penolakan. Catatan lain, termasuk perubahan unit saat disposisi, hanya untuk petugas (pelengkap
     * Bagian 10, prd/log-deviasi.md).
     */
    public function up(): void
    {
        Schema::table('riwayat_status', function (Blueprint $table) {
            $table->boolean('tampil_ke_pelapor')->default(false)->after('catatan');
        });
    }

    public function down(): void
    {
        Schema::table('riwayat_status', function (Blueprint $table) {
            $table->dropColumn('tampil_ke_pelapor');
        });
    }
};
