<?php

namespace Tests\Concerns;

use App\Enums\Peran;
use App\Models\User;
use App\Services\Wbs\KodeAksesWbs;
use Illuminate\Support\Str;

/**
 * Kanal WBS internal (revisi 20-11): kode akses diatur lewat layanan Pengaturan dengan nilai acak per test, sehingga
 * tidak ada kode akses yang tertanam di test atau commit.
 */
trait MembukaKanalWbs
{
    /**
     * Mengatur kode akses acak dan mengembalikannya.
     */
    protected function aturKodeAkses(?string $petunjuk = null): string
    {
        $kode = Str::random(12);
        $pengatur = User::factory()->peran(Peran::SuperAdmin)->denganMfa()->create();

        app(KodeAksesWbs::class)->simpan($pengatur, $kode, $petunjuk);

        return $kode;
    }

    protected function tokenAkses(): string
    {
        return KodeAksesWbs::terbitkanToken();
    }
}
