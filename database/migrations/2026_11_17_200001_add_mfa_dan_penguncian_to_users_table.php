<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 2FA aplikasi autentikator (MFA Filament 5) dan penguncian akun setelah 5 kali gagal login (US-26).
     * Kolom app_authentication_* mengikuti nama bawaan Filament (prd/log-deviasi.md, baris users).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            $table->unsignedTinyInteger('gagal_login')->default(0)->after('jenis_akun');
            $table->timestamp('terkunci_pada')->nullable()->after('gagal_login');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'app_authentication_secret',
                'app_authentication_recovery_codes',
                'gagal_login',
                'terkunci_pada',
            ]);
        });
    }
};
