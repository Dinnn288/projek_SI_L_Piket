<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        

        DB::statement('ALTER TABLE `temporary_reporters` MODIFY `class_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `temporary_reporters` MODIFY `student_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `temporary_reporters` MODIFY `activated_by` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `duty_schedules` MODIFY `teacher_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `reports` MODIFY `category_id` BIGINT NOT NULL');


        DB::statement('ALTER TABLE `reports` MODIFY `assigned_teacher_id` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `notifications` MODIFY `report_id` BIGINT UNSIGNED NULL');

        
        $this->addUniqueIfMissing('students', 'user_id', 'students_user_id_unique');
        $this->addUniqueIfMissing('teachers', 'user_id', 'teachers_user_id_unique');

        
        $this->addForeignKey('students', 'user_id', 'users_baru', 'id', 'students_user_id_foreign', 'CASCADE', 'CASCADE');
        $this->addForeignKey('students', 'class_id', 'classes', 'id', 'students_class_id_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('teachers', 'user_id', 'users_baru', 'id', 'teachers_user_id_foreign', 'CASCADE', 'CASCADE');
        $this->addForeignKey('class_representatives', 'class_id', 'classes', 'id', 'class_representatives_class_id_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('class_representatives', 'student_id', 'students', 'id', 'class_representatives_student_id_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('temporary_reporters', 'class_id', 'classes', 'id', 'temporary_reporters_class_id_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('temporary_reporters', 'student_id', 'students', 'id', 'temporary_reporters_student_id_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('temporary_reporters', 'activated_by', 'users_baru', 'id', 'temporary_reporters_activated_by_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('duty_schedules', 'teacher_id', 'teachers', 'id', 'duty_schedules_teacher_id_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('reports', 'student_id', 'students', 'id', 'reports_student_id_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('reports', 'class_id', 'classes', 'id', 'reports_class_id_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('reports', 'category_id', 'report_categories', 'id', 'reports_category_id_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('reports', 'assigned_teacher_id', 'teachers', 'id', 'reports_assigned_teacher_id_foreign', 'SET NULL', 'CASCADE');
        $this->addForeignKey('report_attachments', 'report_id', 'reports', 'id', 'report_attachments_report_id_foreign', 'CASCADE', 'CASCADE');
        $this->addForeignKey('report_histories', 'report_id', 'reports', 'id', 'report_histories_report_id_foreign', 'CASCADE', 'CASCADE');
        $this->addForeignKey('report_histories', 'user_id', 'users_baru', 'id', 'report_histories_user_id_foreign', 'RESTRICT', 'CASCADE');
        $this->addForeignKey('notifications', 'user_id', 'users_baru', 'id', 'notifications_user_id_foreign', 'CASCADE', 'CASCADE');
        $this->addForeignKey('notifications', 'report_id', 'reports', 'id', 'notifications_report_id_foreign', 'SET NULL', 'CASCADE');
    }

   
    public function down(): void
    {
        $constraints = [
            ['students', 'students_user_id_foreign'],
            ['students', 'students_class_id_foreign'],
            ['teachers', 'teachers_user_id_foreign'],
            ['class_representatives', 'class_representatives_class_id_foreign'],
            ['class_representatives', 'class_representatives_student_id_foreign'],
            ['temporary_reporters', 'temporary_reporters_class_id_foreign'],
            ['temporary_reporters', 'temporary_reporters_student_id_foreign'],
            ['temporary_reporters', 'temporary_reporters_activated_by_foreign'],
            ['duty_schedules', 'duty_schedules_teacher_id_foreign'],
            ['reports', 'reports_student_id_foreign'],
            ['reports', 'reports_class_id_foreign'],
            ['reports', 'reports_category_id_foreign'],
            ['reports', 'reports_assigned_teacher_id_foreign'],
            ['report_attachments', 'report_attachments_report_id_foreign'],
            ['report_histories', 'report_histories_report_id_foreign'],
            ['report_histories', 'report_histories_user_id_foreign'],
            ['notifications', 'notifications_user_id_foreign'],
            ['notifications', 'notifications_report_id_foreign'],
        ];

        foreach ($constraints as [$table, $constraint]) {
            if ($this->constraintExists($table, $constraint)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
            }
        }

        $this->dropIndexIfExists('students', 'students_user_id_unique');
        $this->dropIndexIfExists('teachers', 'teachers_user_id_unique');
    }

    private function addForeignKey(
        string $table,
        string $column,
        string $parentTable,
        string $parentColumn,
        string $constraint,
        string $onDelete,
        string $onUpdate
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if ($this->constraintExists($table, $constraint)) {
            return;
        }

        DB::statement(
            "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` " .
            "FOREIGN KEY (`{$column}`) REFERENCES `{$parentTable}` (`{$parentColumn}`) " .
            "ON DELETE {$onDelete} ON UPDATE {$onUpdate}"
        );
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return (bool) DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $constraint]
        );
    }

    private function addUniqueIfMissing(string $table, string $column, string $index): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        if (! $exists) {
            DB::statement("ALTER TABLE `{$table}` ADD UNIQUE `{$index}` (`{$column}`)");
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS ' .
            'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        if ($exists) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
