<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'teachers';

    private const COLUMN = 'nip';

    private const INDEX = 'teachers_nip_unique';

    /**
     * Tambah UNIQUE teachers.nip (B2.2-M3A).
     *
     * Prasyarat (abort bila gagal): tanpa NULL/kosong/spasi-only dan tanpa
     * duplikat (cek exact ber-collation + LOWER(TRIM) sebagai pemeriksaan
     * tambahan/aproksimasi, bukan pengganti identik seluruh aturan
     * kesetaraan utf8mb4_unicode_ci — jaring akhir tetap penolakan 1062
     * oleh MySQL sendiri). Idempoten: lewati bila indeks bernama sama pada
     * kolom nip sudah ada. Tidak mengubah tipe/panjang/collation/null, FK,
     * tabel lain, atau indeks lain.
     */
    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if ($this->indexExists()) {
            return;
        }

        $this->assertPrerequisites();

        DB::statement('ALTER TABLE `'.self::TABLE.'` ADD UNIQUE `'.self::INDEX.'` (`'.self::COLUMN.'`)');
    }

    /**
     * Hapus hanya indeks milik migration ini, dan hanya bila ada.
     * Rollback melepas perlindungan UNIQUE; data tidak disentuh.
     */
    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->indexExists()) {
            // Selaras pola proyek (dropIndexIfExists): satu sintaks
            // ALTER TABLE ... DROP INDEX untuk MySQL/MariaDB. Lingkungan
            // pengujian proyek memakai MySQL si_l_piket_test, bukan SQLite,
            // sehingga cabang SQLite yang menyimpang dihapus.
            DB::statement('ALTER TABLE `'.self::TABLE.'` DROP INDEX `'.self::INDEX.'`');
        }
    }

    /**
     * @throws \RuntimeException
     */
    private function assertPrerequisites(): void
    {
        $bad = (int) (DB::selectOne(
            'SELECT COUNT(*) AS c FROM `'.self::TABLE.'` WHERE `'.self::COLUMN.'` IS NULL OR TRIM(`'.self::COLUMN.'`) = ""'
        )->c ?? 0);

        if ($bad > 0) {
            throw new \RuntimeException(
                'Migration dibatalkan: ditemukan '.$bad.' NIP NULL/kosong/spasi-only pada teachers.nip.'
            );
        }

        $dupExact = (int) (DB::selectOne(
            'SELECT COUNT(*) AS c FROM (SELECT `'.self::COLUMN.'` FROM `'.self::TABLE.'` GROUP BY `'.self::COLUMN.'` HAVING COUNT(*) > 1) AS d'
        )->c ?? 0);

        if ($dupExact > 0) {
            throw new \RuntimeException(
                'Migration dibatalkan: ditemukan '.$dupExact.' kelompok NIP duplikat pada teachers.nip.'
            );
        }

        // Pemeriksaan tambahan (aproksimasi, bukan identik dengan seluruh
        // aturan kesetaraan utf8mb4_unicode_ci): varian case + spasi tepi.
        $dupFolded = (int) (DB::selectOne(
            'SELECT COUNT(*) AS c FROM (SELECT LOWER(TRIM(`'.self::COLUMN.'`)) AS k FROM `'.self::TABLE.'` GROUP BY k HAVING COUNT(*) > 1) AS d'
        )->c ?? 0);

        if ($dupFolded > 0) {
            throw new \RuntimeException(
                'Migration dibatalkan: ditemukan '.$dupFolded.' kelompok NIP duplikat case-insensitive pada teachers.nip.'
            );
        }
    }

    /**
     * Indeks dianggap sudah ada hanya bila nama cocok DAN mencakup kolom
     * nip — mencegah indeks lain bernama sama pada kolom berbeda disangka
     * sebagai UNIQUE NIP.
     */
    private function indexExists(): bool
    {
        return (bool) DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS '.
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? AND COLUMN_NAME = ?',
            [self::TABLE, self::INDEX, self::COLUMN]
        );
    }
};
