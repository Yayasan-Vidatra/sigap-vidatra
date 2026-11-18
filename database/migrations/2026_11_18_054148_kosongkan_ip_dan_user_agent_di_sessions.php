<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mengosongkan alamat IP dan user agent yang sudah tercatat sebelum penyimpanan sesi diganti
     * App\Services\Sesi\PenanganSesiTanpaJejak (aturan proyek no. 5). Kolomnya tetap ada karena
     * penangan sesi database Laravel selalu menulisnya, kini dengan nilai null.
     */
    public function up(): void
    {
        DB::table('sessions')->update(['ip_address' => null, 'user_agent' => null]);
    }

    /**
     * Nilai yang sudah dikosongkan tidak dapat dikembalikan.
     */
    public function down(): void
    {
        //
    }
};
