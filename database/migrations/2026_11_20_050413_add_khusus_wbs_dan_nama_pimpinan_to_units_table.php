<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisi 20-11 (log deviasi, PT Vidatra Jaya Makmur): unit khusus_wbs hanya tampil sebagai tempat kejadian WBS,
     * tidak di Pengaduan. nama_pimpinan untuk pimpinan unit tanpa akun panel, dipakai pencocokan nama terlapor seperti
     * Kepala Sekolah; dikelola di Pengaturan > Unit.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('khusus_wbs')->default(false)->after('aktif');
            $table->string('nama_pimpinan', 150)->nullable()->after('khusus_wbs');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['khusus_wbs', 'nama_pimpinan']);
        });
    }
};
