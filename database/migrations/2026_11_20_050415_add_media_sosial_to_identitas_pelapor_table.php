<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisi 20-11 (WBS tidak anonim): akun media sosial pelapor, opsional. Bertipe text karena dienkripsi untuk
     * semua baris (maks. 150 karakter sebelum dienkripsi, divalidasi di formulir).
     */
    public function up(): void
    {
        Schema::table('identitas_pelapor', function (Blueprint $table) {
            $table->text('media_sosial')->nullable()->after('no_hp');
        });
    }

    public function down(): void
    {
        Schema::table('identitas_pelapor', function (Blueprint $table) {
            $table->dropColumn('media_sosial');
        });
    }
};
