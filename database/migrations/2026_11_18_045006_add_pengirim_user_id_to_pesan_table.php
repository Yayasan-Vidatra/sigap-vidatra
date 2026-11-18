<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Petugas yang mengirim pesan (UC-05, US-09); kosong untuk pesan pelapor dan sistem (pelengkap Bagian 10,
     * prd/log-deviasi.md).
     */
    public function up(): void
    {
        Schema::table('pesan', function (Blueprint $table) {
            $table->foreignId('pengirim_user_id')->nullable()->after('pengirim')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pesan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pengirim_user_id');
        });
    }
};
