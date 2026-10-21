<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('class_duty_student_schedules')) {
            Schema::create('class_duty_student_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('class_id');
                $table->unsignedBigInteger('student_id');
                $table->enum('day', ['senin', 'selasa', 'rabu', 'kamis', 'jumat']);
                $table->timestamps();

                $table->unique(['class_id', 'student_id', 'day'], 'class_duty_student_schedules_class_student_day_unique');
                $table->index(['class_id', 'day'], 'class_duty_student_schedules_class_day_index');
                $table->index('student_id', 'class_duty_student_schedules_student_id_index');

                $table->foreign('class_id', 'class_duty_student_schedules_class_id_foreign')
                    ->references('id')->on('classes')
                    ->onDelete('restrict')->onUpdate('cascade');

                $table->foreign('student_id', 'class_duty_student_schedules_student_id_foreign')
                    ->references('id')->on('students')
                    ->onDelete('restrict')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_duty_student_schedules');
    }
};