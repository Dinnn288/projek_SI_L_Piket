<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Teacher;
use Carbon\Carbon;

/**
 * Penentu Guru Piket berdasarkan duty_schedules aktual.
 *
 * Struktur aktual duty_schedules: id, teacher_id, day (enum
 * senin|selasa|rabu|kamis|jumat|sabtu), timestamps. Tidak ada kolom
 * jam_mulai/jam_selesai, sehingga penjadwalan bersifat HARIAN:
 * guru yang terjadwal pada hari ini dapat mengambil laporan kapan
 * saja tanpa batasan jam operasional.
 */
class GuruDutyService
{
    public const TIMEZONE = 'Asia/Jakarta';

    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    public static function todayDay(): ?string
    {
        return match ((int) self::now()->dayOfWeek) {
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            default => null, // minggu tidak ada di enum duty_schedules
        };
    }

    public static function isOnDutyDay(Teacher $teacher): bool
    {
        $day = self::todayDay();

        if ($day === null) {
            return false;
        }

        return $teacher->dutySchedules()->where('day', $day)->exists();
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function canClaim(Teacher $teacher): array
    {
        if (! self::isOnDutyDay($teacher)) {
            return ['ok' => false, 'message' => 'Anda tidak sedang bertugas sebagai Guru Piket hari ini.'];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * Laporan lanjutan: status dalam_penanganan yang assignment-nya
     * dibuat sebelum hari ini (assigned_at < awal hari ini) sehingga
     * dapat diambil alih Guru Piket yang sedang bertugas. Laporan yang
     * masih menunggu / selesai / diteruskan dikecualikan.
     */
    public static function isContinuable(Report $report): bool
    {
        if ($report->status !== 'dalam_penanganan') {
            return false;
        }

        if ($report->assigned_teacher_id === null || $report->assigned_at === null) {
            return false;
        }

        return Carbon::parse($report->assigned_at)->lt(Carbon::today());
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function canTakeover(Teacher $teacher, Report $report): array
    {
        if (! self::isContinuable($report)) {
            return ['ok' => false, 'message' => 'Laporan ini tidak dapat diambil alih.'];
        }

        if ((int) $report->assigned_teacher_id === (int) $teacher->id) {
            return ['ok' => false, 'message' => 'Laporan ini masih dalam penanganan Anda.'];
        }

        return self::canClaim($teacher);
    }
}
