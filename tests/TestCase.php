<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    // Guard terpusat B2.2-J: cegah tulis ke database produksi.
    use TestDatabaseGuard;

    protected function setUp(): void
    {
        parent::setUp();

        // Periksa koneksi AKTUAL (bukan config): tolak `si_l_piket`,
        // izinkan sqlite-memory dan `si_l_piket_test`. Test yang me-reconnect
        // setelah setUp wajib memanggil assertTestDatabaseSafe() lagi.
        static::assertTestDatabaseSafe();
    }
}
