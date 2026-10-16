<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

/**
 * Guard isolasi database pengujian (B2.2-J).
 *
 * Aturan: koneksi database AKTUAL (bukan nilai config) tidak boleh
 * `si_l_piket`. Diizinkan: SQLite in-memory (`:memory:`) dan MySQL yang
 * nama database aktualnya tepat `si_l_piket_test`. Guard menolak (fail),
 * tidak pernah mengalihkan koneksi secara diam-diam.
 */
trait TestDatabaseGuard
{
    public static function assertTestDatabaseSafe(?string $connection = null): void
    {
        $name = DB::connection($connection)->getDatabaseName();

        if ($name === 'si_l_piket') {
            static::failForProductionDatabase($connection);
        }
    }

    protected static function failForProductionDatabase(?string $connection = null): never
    {
        $label = $connection ? "koneksi [$connection]" : 'koneksi default';

        throw new \RuntimeException(
            "DITOLAK: pengujian memakai database produksi `si_l_piket` pada $label. "
            .'Gunakan database test `si_l_piket_test` atau SQLite in-memory.'
        );
    }

    /**
     * Override aman untuk test MySQL terisolasi: paksa database test,
     * buka ulang koneksi, lalu verifikasi nama aktual. Menolak bila
     * hasil akhirnya bukan `si_l_piket_test`.
     */
    protected function useIsolatedTestDatabase(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket_test']);
        DB::reconnect('mysql');

        $name = DB::connection()->getDatabaseName();

        if ($name !== 'si_l_piket_test') {
            static::failForProductionDatabase();
        }

        $this->assertEquals('si_l_piket_test', $name);
    }
}
