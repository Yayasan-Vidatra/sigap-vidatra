<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Menolak menjalankan test (termasuk RefreshDatabase) terhadap basis data MySQL/MariaDB yang
     * namanya tidak berakhiran "_testing", agar basis data lokal seperti sigap_vidatra tidak terhapus.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $koneksi = config('database.connections.'.config('database.default'));

        if (in_array($koneksi['driver'] ?? null, ['mysql', 'mariadb'], true)
            && ! str_ends_with((string) ($koneksi['database'] ?? ''), '_testing')) {
            throw new RuntimeException(
                "Test menolak berjalan di basis data \"{$koneksi['database']}\". Pakai basis data berakhiran _testing (lihat .env.testing)."
            );
        }

        return parent::setUpTraits();
    }
}
