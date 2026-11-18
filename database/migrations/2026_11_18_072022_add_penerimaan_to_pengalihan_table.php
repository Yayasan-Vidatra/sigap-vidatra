<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penerima yang mengonfirmasi terima dan catatan tindak lanjutnya (UC-19, US-19, H-17; pelengkap Bagian 10 yang
     * disetujui "menyusul bersama US-19", lihat prd/log-deviasi.md). Catatan tindak lanjut terenkripsi.
     */
    public function up(): void
    {
        Schema::table('pengalihan', function (Blueprint $table) {
            $table->foreignId('diterima_oleh')->nullable()->after('diterima_pada')->constrained('users')->restrictOnDelete();
            $table->text('catatan_tindak_lanjut')->nullable()->after('diterima_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('pengalihan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diterima_oleh');
            $table->dropColumn('catatan_tindak_lanjut');
        });
    }
};
