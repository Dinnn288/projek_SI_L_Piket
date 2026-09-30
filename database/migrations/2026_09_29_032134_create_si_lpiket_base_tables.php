<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. users_baru — akun utama SI-LPiket.
        if (! Schema::hasTable('users_baru')) {
            Schema::create('users_baru', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('username')->unique();
                $table->string('password');
                $table->enum('role', ['admin', 'siswa', 'guru', 'kepala_sekolah'])->nullable();
                $table->enum('status', ['aktif', 'nonaktif'])->nullable();
                $table->timestamps();
            });
        }

        // 2. majors — data jurusan.
        if (! Schema::hasTable('majors')) {
            Schema::create('majors', function (Blueprint $table) {
                $table->id();
                $table->string('name', 200);
                $table->enum('status', ['aktif', 'nonaktif']);
                $table->timestamps();
            });
        }

        // 3. classes — major_id sebagai kolom+index, tanpa FK.
        if (! Schema::hasTable('classes')) {
            Schema::create('classes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('major_id');
                $table->string('name');
                $table->enum('status', ['aktif', 'nonaktif']);
                $table->timestamps();
                $table->index('major_id');
            });
        }

        // 4. students — user_id/class_id kolom+index, tanpa FK.
        if (! Schema::hasTable('students')) {
            Schema::create('students', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('nis', 50)->unique();
                $table->unsignedBigInteger('class_id');
                $table->string('phone', 50)->nullable();
                $table->timestamps();
                $table->index('class_id');
            });
        }

        // 5. teachers — user_id kolom unique, tanpa FK.
        if (! Schema::hasTable('teachers')) {
            Schema::create('teachers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('nip', 50);
                $table->string('phone', 50)->nullable();
                $table->timestamps();
            });
        }

        // 6. class_representatives — kolom+index, tanpa FK.
        if (! Schema::hasTable('class_representatives')) {
            Schema::create('class_representatives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('class_id');
                $table->unsignedBigInteger('student_id');
                $table->enum('type', ['km', 'wkm']);
                $table->timestamps();
                $table->index('class_id');
                $table->index('student_id');
            });
        }

        // 7. temporary_reporters — activated_by dipertahankan, tanpa FK.
        if (! Schema::hasTable('temporary_reporters')) {
            Schema::create('temporary_reporters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('class_id');
                $table->unsignedBigInteger('student_id');
                $table->date('active_date');
                $table->unsignedBigInteger('activated_by');
                $table->timestamps();
                $table->index('class_id');
                $table->index('student_id');
                $table->index('activated_by');
            });
        }

        // 8. duty_schedules — teacher_id kolom+index, tanpa FK.
        if (! Schema::hasTable('duty_schedules')) {
            Schema::create('duty_schedules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('teacher_id');
                $table->enum('day', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu']);
                $table->timestamps();
                $table->index('teacher_id');
            });
        }

        // 9. report_categories.
        if (! Schema::hasTable('report_categories')) {
            Schema::create('report_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->enum('status', ['aktif', 'nonaktif']);
                $table->enum('priority', ['rendah', 'sedang', 'tinggi']);
                $table->timestamps();
            });
        }

        // 10. reports — assigned_teacher_id dipertahankan (nullable), tanpa FK.
        if (! Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('class_id');
                $table->unsignedBigInteger('category_id');
                $table->string('title', 200);
                $table->text('description');
                $table->string('location', 200);
                $table->enum('priority', ['rendah', 'sedang', 'tinggi']);
                $table->enum('status', ['menunggu', 'dalam_penanganan', 'diteruskan', 'selesai']);
                $table->unsignedBigInteger('assigned_teacher_id')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index('student_id');
                $table->index('class_id');
                $table->index('category_id');
                $table->index('assigned_teacher_id');
            });
        }

        // 11. report_attachments — report_id kolom+index, tanpa FK.
        if (! Schema::hasTable('report_attachments')) {
            Schema::create('report_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('report_id');
                $table->string('file_patch');
                $table->string('original_name');
                $table->unsignedBigInteger('file_size');
                $table->string('mime_type', 200);
                $table->timestamps();
                $table->index('report_id');
            });
        }

        // 12. report_histories — report_id/user_id kolom+index, tanpa FK.
        if (! Schema::hasTable('report_histories')) {
            Schema::create('report_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('report_id');
                $table->unsignedBigInteger('user_id');
                $table->enum('action', ['created', 'claimed', 'handled', 'forwarded', 'returned', 'completed']);
                $table->enum('from_status', ['menunggu', 'dalam_penanganan', 'diteruskan', 'selesai'])->nullable();
                $table->enum('to_status', ['menunggu', 'dalam_penanganan', 'diteruskan', 'selesai'])->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index('report_id');
                $table->index('user_id');
            });
        }

        // 13. notifications — report_id nullable, tanpa FK.
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('report_id')->nullable();
                $table->string('title', 200);
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamps();
                $table->index('user_id');
                $table->index('report_id');
            });
        }
    }

    public function down(): void
    {
        
    }
};
