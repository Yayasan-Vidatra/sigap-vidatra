<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom petugas pada users (Rancangan r1 Bagian 10). Kolom 2FA menyusul di Hari 3 (US-26)
     * mengikuti MFA Filament (lihat prd/log-deviasi.md).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('peran', 30)->nullable()->after('email');
            $table->foreignId('unit_id')->nullable()->after('peran')->constrained('units')->restrictOnDelete();
            $table->string('jenis_akun', 10)->default('lokal')->after('unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['peran', 'jenis_akun']);
        });
    }
};
