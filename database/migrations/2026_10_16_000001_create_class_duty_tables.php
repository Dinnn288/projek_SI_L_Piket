<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistem Piket Kelas — 4 tabel BARU, non-destruktif.
 *
 * - Tidak mengubah/menghapus tabel lama apa pun.
 * - Idempoten: setiap create dijaga hasTable.
 * - FK: master RESTRICT, anak laporan CASCADE, verifikator SET NULL.
 * - UNIQUE: (class_id, day) jadwal; (class_id, report_date) laporan.
 * - Kompatibel MySQL + SQLite (tanpa raw MODIFY/information_schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_duty_schedules')) {
            Schema::create('class_duty_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('class_id');
                $table->enum('day', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu']);
                $table->timestamps();
                $table->unique(['class_id', 'day'], 'class_duty_schedules_class_day_unique');
                $table->index('day', 'class_duty_schedules_day_index');
                $table->foreign('class_id', 'class_duty_schedules_class_id_foreign')
                    ->references('id')->on('classes')
                    ->onDelete('restrict')->onUpdate('cascade');
            });
        }

        if (! Schema::hasTable('class_duty_reports')) {
            Schema::create('class_duty_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('class_id');
                $table->date('report_date');
                $table->unsignedBigInteger('student_id');
                $table->string('title', 200)->default('Laporan Pelaksanaan Piket Kelas');
                $table->json('checklist')->nullable();
                $table->text('notes')->nullable();
                $table->enum('status', ['menunggu_verifikasi', 'perlu_diperbaiki', 'disetujui'])
                    ->default('menunggu_verifikasi');
                $table->unsignedBigInteger('verified_by_teacher_id')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->text('return_note')->nullable();
                $table->timestamps();
                $table->unique(['class_id', 'report_date'], 'class_duty_reports_class_date_unique');
                $table->index(['status', 'report_date'], 'class_duty_reports_status_date_index');
                $table->foreign('class_id', 'class_duty_reports_class_id_foreign')
                    ->references('id')->on('classes')
                    ->onDelete('restrict')->onUpdate('cascade');
                $table->foreign('student_id', 'class_duty_reports_student_id_foreign')
                    ->references('id')->on('students')
                    ->onDelete('restrict')->onUpdate('cascade');
                $table->foreign('verified_by_teacher_id', 'class_duty_reports_verified_by_foreign')
                    ->references('id')->on('teachers')
                    ->onDelete('set null')->onUpdate('cascade');
            });
        }

        if (! Schema::hasTable('class_duty_report_histories')) {
            Schema::create('class_duty_report_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('class_duty_report_id');
                $table->unsignedBigInteger('user_id');
                $table->enum('action', ['created', 'resubmitted', 'returned', 'approved']);
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->foreign('class_duty_report_id', 'class_duty_histories_report_id_foreign')
                    ->references('id')->on('class_duty_reports')
                    ->onDelete('cascade')->onUpdate('cascade');
                $table->foreign('user_id', 'class_duty_histories_user_id_foreign')
                    ->references('id')->on('users_baru')
                    ->onDelete('restrict')->onUpdate('cascade');
            });
        }

        if (! Schema::hasTable('class_duty_report_attachments')) {
            Schema::create('class_duty_report_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('class_duty_report_id');
                $table->string('file_patch');
                $table->string('original_name');
                $table->unsignedBigInteger('file_size');
                $table->string('mime_type', 200);
                $table->timestamps();
                $table->index('class_duty_report_id', 'class_duty_attachments_report_id_index');
                $table->foreign('class_duty_report_id', 'class_duty_attachments_report_id_foreign')
                    ->references('id')->on('class_duty_reports')
                    ->onDelete('cascade')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_duty_report_attachments');
        Schema::dropIfExists('class_duty_report_histories');
        Schema::dropIfExists('class_duty_reports');
        Schema::dropIfExists('class_duty_schedules');
    }
};
