<?php

namespace App\Services;

use App\Models\ClassDutyReport;
use App\Models\ClassDutySchedule;
use App\Models\ClassDutyStudentSchedule;
use Carbon\Carbon;

/**
 * Layanan Sistem Piket Kelas (terpisah dari GuruDutyService).
 *
 * - Sumber tanggal/hari: server Asia/Jakarta.
 * - Jadwal kelas: class_duty_schedules (class_id, day senin-jumat).
 * - Jadwal siswa: class_duty_student_schedules (class_id, student_id, day senin-jumat).
 * - Laporan: class_duty_reports (class_id, report_date).
 * - Model piket: siswa yang dijadwalkan pada hari tersebut berhak mengirim laporan.
 *   Satu laporan per kelas per tanggal (first-wins via UNIQUE).
 */
class ClassDutyService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public const CHECKLIST_ITEMS = [
        'menyapu' => 'Menyapu kelas',
        'mengepel' => 'Mengepel lantai',
        'papan_tulis' => 'Membersihkan papan tulis',
        'sampah' => 'Membuang sampah',
        'meja_kursi' => 'Merapikan meja kursi',
    ];

    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    public static function todayDate(): string
    {
        return self::now()->toDateString();
    }

    public static function todayDay(): ?string
    {
        return match ((int) self::now()->dayOfWeek) {
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            default => null,
        };
    }

    /**
     * Mendapatkan nama hari dari tanggal (Senin=1, Minggu=0/null).
     */
    public static function dayFromDate(string $date): ?string
    {
        $carbon = Carbon::parse($date, self::TIMEZONE);

        return match ((int) $carbon->dayOfWeek) {
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            default => null,
        };
    }

    /**
     * Cek apakah KELAS dijadwalkan pada hari tertentu (level kelas).
     */
    public static function isScheduled(int $classId, ?string $day = null): bool
    {
        $day ??= self::todayDay();

        if ($day === null) {
            return false;
        }

        return ClassDutySchedule::where('class_id', $classId)
            ->where('day', $day)
            ->exists();
    }

    /**
     * Cek apakah KELAS memiliki siswa yang dijadwalkan pada hari tertentu.
     */
    public static function hasStudentsScheduled(int $classId, ?string $day = null): bool
    {
        $day ??= self::todayDay();

        if ($day === null) {
            return false;
        }

        return ClassDutyStudentSchedule::where('class_id', $classId)
            ->where('day', $day)
            ->exists();
    }

    /**
     * Cek apakah SISWA tertentu dijadwalkan pada hari tertentu.
     * Validasi integritas: siswa harus terdaftar di kelas yang sama.
     */
    public static function isStudentScheduled(int $studentId, ?string $day = null): bool
    {
        $day ??= self::todayDay();

        if ($day === null) {
            return false;
        }

        return ClassDutyStudentSchedule::where('student_id', $studentId)
            ->where('day', $day)
            ->exists();
    }

    /**
     * Cek apakah siswa dijadwalkan pada kelas yang benar (sesuai students.class_id).
     */
    public static function isStudentScheduledInClass(int $studentId, int $classId, ?string $day = null): bool
    {
        $day ??= self::todayDay();

        if ($day === null) {
            return false;
        }

        return ClassDutyStudentSchedule::where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('day', $day)
            ->exists();
    }

    /**
     * Ambil laporan piket untuk kelas dan tanggal.
     */
    public static function reportFor(int $classId, ?string $date = null): ?ClassDutyReport
    {
        $date ??= self::todayDate();

        return ClassDutyReport::where('class_id', $classId)
            ->where('report_date', $date)
            ->first();
    }

    /**
     * Status pemantauan untuk (kelas, tanggal): salah satu dari
     * tidak_dijadwalkan | tidak_ada_piket | belum_melapor | menunggu_verifikasi
     * | perlu_diperbaiki | sudah_piket
     *
     * - tidak_dijadwalkan: kelas tidak punya jadwal kelas pada hari itu
     * - tidak_ada_piket: kelas punya jadwal tapi tidak ada siswa yang dijadwalkan
     * - belum_melapor: kelas terjadwal & punya siswa terjadwal, tapi belum lapor
     * - menunggu_verifikasi: laporan dikirim, menunggu verifikasi guru
     * - perlu_diperbaiki: laporan dikembalikan guru, perlu perbaikan
     * - sudah_piket: laporan disetujui guru
     */
    public static function statusFor(int $classId, ?string $date = null, ?string $day = null): string
    {
        $date ??= self::todayDate();
        $day ??= self::todayDay();

        if ($day === null) {
            return 'tidak_dijadwalkan';
        }

        $scheduled = ClassDutySchedule::where('class_id', $classId)
            ->where('day', $day)
            ->exists();

        if (! $scheduled) {
            return 'tidak_dijadwalkan';
        }

        $hasStudents = ClassDutyStudentSchedule::where('class_id', $classId)
            ->where('day', $day)
            ->exists();

        if (! $hasStudents) {
            return 'tidak_ada_piket';
        }

        $report = ClassDutyReport::where('class_id', $classId)
            ->where('report_date', $date)
            ->first();

        if (! $report) {
            return 'belum_melapor';
        }

        return match ($report->status) {
            ClassDutyReport::STATUS_MENUNGGU => 'menunggu_verifikasi',
            ClassDutyReport::STATUS_PERBAIKI => 'perlu_diperbaiki',
            ClassDutyReport::STATUS_DISETUJUI => 'sudah_piket',
            default => 'belum_melapor',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'tidak_dijadwalkan' => 'Tidak dijadwalkan',
            'tidak_ada_piket' => 'Tidak ada piket',
            'belum_melapor' => 'Belum melapor',
            'menunggu_verifikasi' => 'Menunggu verifikasi',
            'perlu_diperbaiki' => 'Perlu diperbaiki',
            'sudah_piket' => 'Sudah piket',
            default => $status,
        };
    }

    /**
     * Tingkat kelas diturunkan dari nama (X/XI/XII) karena tabel
     * classes tidak memiliki kolom tingkat. Kembalikan '-' bila
     * tidak dapat dikenali (tanpa data palsu).
     */
    public static function gradeFromName(?string $name): string
    {
        if (! is_string($name) || $name === '') {
            return '-';
        }

        $upper = strtoupper(trim($name));

        if (str_starts_with($upper, 'XII')) {
            return 'XII';
        }

        if (str_starts_with($upper, 'XI')) {
            return 'XI';
        }

        if (str_starts_with($upper, 'X ') || str_starts_with($upper, 'X-')) {
            return 'X';
        }

        // Pola "KELAS X ...", "10 ...", dsb.
        if (preg_match('/\bX{1,3}\b/', $upper, $m)) {
            return $m[0];
        }

        if (preg_match('/\b(10|11|12)\b/', $upper, $m)) {
            return match ($m[1]) {
                '10' => 'X',
                '11' => 'XI',
                '12' => 'XII',
                default => '-',
            };
        }

        return '-';
    }
}