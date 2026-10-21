<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sabtuCount = DB::table('class_duty_schedules')->where('day', 'sabtu')->count();

        if ($sabtuCount > 0) {
            throw new \RuntimeException(
                "Terdapat {$sabtuCount} jadwal Sabtu di class_duty_schedules. " .
                "Hapus atau pindahkan data Sabtu terlebih dahulu sebelum menjalankan migrasi ini."
            );
        }

        DB::statement("ALTER TABLE class_duty_schedules MODIFY day ENUM('senin','selasa','rabu','kamis','jumat') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE class_duty_schedules MODIFY day ENUM('senin','selasa','rabu','kamis','jumat','sabtu') NOT NULL");
    }
};