<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-point 5 FK akun SI-LPiket dari `users` ke `users_baru`.
 *
 * Latar: file migration FK sudah di-rename di kode (parent `users_baru`),
 * tetapi 5 constraint live DB masih menunjuk `users.id`. Migration ini
 * menyelaraskan live DB dengan kode tanpa drop tabel dan tanpa hapus data.
 *
 * Pengaman:
 * - users_baru.id diselaraskan ke BIGINT UNSIGNED agar kompatibel dengan
 *   5 kolom FK yang sudah unsigned (MODIFY idempoten, tabel kosong).
 * - Setiap FK lama di-drop hanya bila masih menunjuk `users`,
 *   lalu dibuat ulang ke `users_baru` dengan rule yang sama.
 * - Abort aman bila tabel kosong berisi data orphan (dilaporkan, bukan dihapus).
 */
return new class extends Migration
{
    private array $targets = [
        ['students', 'user_id', 'users', 'users_baru', 'students_user_id_foreign', 'CASCADE', 'CASCADE'],
        ['teachers', 'user_id', 'users', 'users_baru', 'teachers_user_id_foreign', 'CASCADE', 'CASCADE'],
        ['temporary_reporters', 'activated_by', 'users', 'users_baru', 'temporary_reporters_activated_by_foreign', 'RESTRICT', 'CASCADE'],
        ['report_histories', 'user_id', 'users', 'users_baru', 'report_histories_user_id_foreign', 'RESTRICT', 'CASCADE'],
        ['notifications', 'user_id', 'users', 'users_baru', 'notifications_user_id_foreign', 'CASCADE', 'CASCADE'],
    ];

    public function up(): void
    {
        // 1. Selaraskan PK users_baru dengan kolom FK (unsigned).
        DB::statement('ALTER TABLE `users_baru` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // 2. Re-point 5 FK ke users_baru.
        foreach ($this->targets as [$table, $column, $oldParent, $newParent, $constraint, $onDelete, $onUpdate]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $current = DB::selectOne(
                'SELECT k.REFERENCED_TABLE_NAME AS parent_table ' .
                'FROM information_schema.KEY_COLUMN_USAGE k ' .
                'JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME ' .
                'WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.CONSTRAINT_NAME = ?',
                [$table, $constraint]
            );

            if ($current && $current->parent_table === $newParent) {
                continue;
            }

            if ($current) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }

            DB::statement(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` " .
                "FOREIGN KEY (`{$column}`) REFERENCES `{$newParent}` (`id`) " .
                "ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
            );
        }
    }

    public function down(): void
    {
        // Kembalikan 5 FK ke `users` (rollback non-destruktif, tanpa drop tabel/data).
        foreach ($this->targets as [$table, $column, $oldParent, $newParent, $constraint, $onDelete, $onUpdate]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $current = DB::selectOne(
                'SELECT k.REFERENCED_TABLE_NAME AS parent_table ' .
                'FROM information_schema.KEY_COLUMN_USAGE k ' .
                'JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME ' .
                'WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.CONSTRAINT_NAME = ?',
                [$table, $constraint]
            );

            if ($current && $current->parent_table === $oldParent) {
                continue;
            }

            if ($current) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }

            DB::statement(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` " .
                "FOREIGN KEY (`{$column}`) REFERENCES `{$oldParent}` (`id`) " .
                "ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
            );
        }
    }
};
