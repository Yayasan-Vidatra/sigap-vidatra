<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti penyelesaian PIC Unit (UC-10, US-12) adalah lampiran yang terikat pada disposisinya, sehingga
     * terbedakan dari lampiran pelapor (pelengkap Bagian 10, prd/log-deviasi.md).
     */
    public function up(): void
    {
        Schema::table('lampiran', function (Blueprint $table) {
            $table->foreignId('disposisi_id')->nullable()->after('pesan_id')->constrained('disposisi')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lampiran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disposisi_id');
        });
    }
};
