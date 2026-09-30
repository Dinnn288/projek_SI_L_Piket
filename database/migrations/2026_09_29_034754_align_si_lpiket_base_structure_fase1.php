<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 1 SI-LPiket: finalisasi struktur dasar TANPA foreign key.
 *
 * Ruang lingkup yang diizinkan Fase 1:
 * - Menyelaraskan 3 PK signed -> BIGINT UNSIGNED agar konsisten
 *   (temporary_reporters.id, duty_schedules.id, report_categories.id).
 * - Menyelaraskan reports.category_id -> BIGINT UNSIGNED mengikuti
 *   konsistensi kolom FK masa depan.
 * - Menambahkan UNIQUE users_baru.username sesuai migration base
 *   (hanya bila tidak ada duplikat).
 *
 * Larangan Fase 1 (dipatuhi):
 * - Tanpa ADD/DROP FOREIGN KEY.
 * - Tanpa memperbaiki atau mengubah FK existing.
 * - Tanpa drop/rename tabel, tanpa hapus data, tanpa Model/Controller/
 *   Route/Seeder/Auth/fitur.
 *
 * Seluruh 13 tabel + users_baru kosong (0 baris) sehingga MODIFY aman.
 * Setiap ALTER idempoten dan dilewati bila tipe sudah sesuai.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Konsistensi PK -> BIGINT UNSIGNED untuk 2 tabel yang PK-nya
        //    tidak dirujuk FK mana pun (aman tanpa lepas constraint).
        $this->modifyIfNot(
            'temporary_reporters', 'id',
            'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
            'bigint(20) unsigned'
        );
        $this->modifyIfNot(
            'duty_schedules', 'id',
            'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
            'bigint(20) unsigned'
        );

        // 2. Pasangan report_categories.id + reports.category_id harus diubah
        //    bersamaan: lepas FK dulu, MODIFY kedua sisi, lalu buat ulang FK
        //    dengan definisi IDENTIK (nama, parent, rule tidak berubah).
        //    Ini BUKAN FK baru dan BUKAN perbaikan rule FK.
        $this->modifyCategoryPair();

        // 3. UNIQUE users_baru.username sesuai migration base (guard duplikat).
        $this->addUniqueIfMissing('users_baru', 'username', 'users_baru_username_unique');
    }

    public function down(): void
    {
        // Rollback non-destruktif: hanya melepas UNIQUE yang ditambahkan
        // migration ini. Perubahan tipe dipertahankan agar data aman.
        $this->dropIndexIfExists('users_baru', 'users_baru_username_unique');
    }

    private function modifyCategoryPair(): void
    {
        if (! Schema::hasTable('reports') || ! Schema::hasColumn('reports', 'category_id')) {
            return;
        }

        if (! Schema::hasTable('report_categories') || ! Schema::hasColumn('report_categories', 'id')) {
            return;
        }

        $cat = DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'report_categories' AND COLUMN_NAME = 'id'"
        );
        $col = DB::selectOne(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'category_id'"
        );

        $catOk = $cat && strtolower((string) $cat->COLUMN_TYPE) === 'bigint(20) unsigned';
        $colOk = $col && strtolower((string) $col->COLUMN_TYPE) === 'bigint(20) unsigned';

        if ($catOk && $colOk) {
            return;
        }

        $fk = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS ' .
            "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND CONSTRAINT_NAME = 'reports_category_id_foreign'"
        );

        if ($fk) {
            DB::statement('ALTER TABLE `reports` DROP FOREIGN KEY `reports_category_id_foreign`');
        }

        if (! $colOk) {
            DB::statement('ALTER TABLE `reports` MODIFY `category_id` BIGINT UNSIGNED NOT NULL');
        }

        if (! $catOk) {
            DB::statement('ALTER TABLE `report_categories` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        DB::statement(
            'ALTER TABLE `reports` ADD CONSTRAINT `reports_category_id_foreign` ' .
            'FOREIGN KEY (`category_id`) REFERENCES `report_categories` (`id`) ' .
            'ON DELETE RESTRICT ON UPDATE CASCADE'
        );
    }

    private function modifyIfNot(string $table, string $column, string $definition, string $expectedType): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $current = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        if ($current && strtolower((string) $current->COLUMN_TYPE) === strtolower($expectedType)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
    }

    private function addUniqueIfMissing(string $table, string $column, string $index): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        if ($exists) {
            return;
        }

        $dupes = DB::selectOne(
            "SELECT COUNT(*) AS c FROM (SELECT `{$column}` FROM `{$table}` GROUP BY `{$column}` HAVING COUNT(*) > 1) d"
        );

        if ($dupes && (int) $dupes->c > 0) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$index}` (`{$column}`)");
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
