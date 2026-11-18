<?php

namespace App\Services\Sesi;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Penyimpanan sesi database yang tidak pernah mencatat alamat IP maupun user agent, untuk semua pengguna
 * (aturan proyek no. 5, Rancangan r1 Bagian 9, NF-02). Kolom ip_address dan user_agent di tabel sessions
 * selalu diisi null, sehingga sesi yang dibuka sebelum pelapor membuka /wbs tidak dapat dikaitkan dengan
 * alamat atau perangkatnya. Didaftarkan sebagai pengganti driver "database" di AppServiceProvider.
 */
class PenanganSesiTanpaJejak extends DatabaseSessionHandler
{
    /**
     * @return null
     */
    protected function ipAddress()
    {
        return null;
    }

    /**
     * @return null
     */
    protected function userAgent()
    {
        return null;
    }
}
