<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penunjukan investigator dan rekomendasi WBS (UC-13, US-17; pelengkap Bagian 10 yang disetujui "menyusul
     * bersama US-17", lihat prd/log-deviasi.md). Keduanya hanya untuk WBS; rekomendasi terenkripsi.
     */
    public function up(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->foreignId('investigator_user_id')->nullable()->after('kesimpulan')->constrained('users')->restrictOnDelete();
            $table->text('rekomendasi')->nullable()->after('investigator_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('investigator_user_id');
            $table->dropColumn('rekomendasi');
        });
    }
};
