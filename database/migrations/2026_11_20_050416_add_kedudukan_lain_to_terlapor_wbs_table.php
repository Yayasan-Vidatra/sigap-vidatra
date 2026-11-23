<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisi 20-11 (kedudukan "Lain-lain"): kedudukan terlapor yang ditulis pelapor, terenkripsi (maks. 100 karakter
     * sebelum dienkripsi, divalidasi di formulir).
     */
    public function up(): void
    {
        Schema::table('terlapor_wbs', function (Blueprint $table) {
            $table->text('kedudukan_lain')->nullable()->after('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('terlapor_wbs', function (Blueprint $table) {
            $table->dropColumn('kedudukan_lain');
        });
    }
};
