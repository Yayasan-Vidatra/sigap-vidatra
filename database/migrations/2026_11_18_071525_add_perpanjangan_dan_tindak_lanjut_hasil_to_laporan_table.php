<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan perpanjangan investigasi yang menunggu persetujuan penerima hasil (UC-13 1a), dan penandaan hasil WBS
     * yang sudah ditindaklanjuti penerima hasil (H-16, US-21). Hanya untuk WBS. Pelengkap Bagian 10 berstatus
     * Diusulkan di prd/log-deviasi.md.
     */
    public function up(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->timestamp('perpanjangan_diajukan_pada')->nullable()->after('perpanjangan_disetujui_oleh');
            $table->timestamp('hasil_ditindaklanjuti_pada')->nullable()->after('rekomendasi');
            $table->foreignId('hasil_ditindaklanjuti_oleh')->nullable()->after('hasil_ditindaklanjuti_pada')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hasil_ditindaklanjuti_oleh');
            $table->dropColumn(['perpanjangan_diajukan_pada', 'hasil_ditindaklanjuti_pada']);
        });
    }
};
