<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\ClassDutyReport;
use App\Models\ClassDutySchedule;
use App\Models\Notification;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Services\ClassDutyService;
use App\Services\GuruDutyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private function currentTeacher(Request $request): Teacher
    {
        // B2.1: profil guru yatim ditangani sebagai 403 terkendali (bukan
        // 500): arahkan admin melengkapi NIP resmi via Admin → Guru.
        try {
            return Teacher::resolveForUser($request->user());
        } catch (\App\Exceptions\MissingTeacherProfile $e) {
            abort(403, $e->getMessage());
        }
    }

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);
        $day = GuruDutyService::todayDay();
        $onDutyDay = GuruDutyService::isOnDutyDay($teacher);

        $waiting = collect();
        $waitingCount = 0;
        $continued = collect();
        $continuedCount = 0;

        if ($onDutyDay) {
            $waitingQuery = Report::where('status', 'menunggu')
                ->whereNull('assigned_teacher_id')
                ->with(['category', 'schoolClass', 'student.userBaru']);
            $waitingCount = (clone $waitingQuery)->count();
            $waiting = (clone $waitingQuery)->latest()->take(10)->get();

            $continuedQuery = Report::where('status', 'dalam_penanganan')
                ->whereNotNull('assigned_teacher_id')
                ->where('assigned_teacher_id', '!=', $teacher->id)
                ->where('assigned_at', '<', Carbon::today())
                ->with(['category', 'schoolClass', 'student.userBaru', 'assignedTeacher.userBaru']);
            $continuedCount = (clone $continuedQuery)->count();
            $continued = (clone $continuedQuery)->latest()->take(10)->get();
        }

        $mine = $teacher->assignedReports()
            ->with(['category', 'schoolClass'])
            ->latest()
            ->take(10)
            ->get();

        $mineActive = $teacher->assignedReports()->where('status', 'dalam_penanganan')->count();
        $mineTotal = $teacher->assignedReports()->count();

        $notifications = Notification::where('user_id', $request->user()->id)
            ->with('report')
            ->latest()
            ->limit(3)
            ->get();
        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        $serverNow = GuruDutyService::now();

        // Ringkasan piket kelas tanggal berjalan (data aktual, tanpa angka statis).
        $classDate = ClassDutyService::todayDate();
        $classDay = ClassDutyService::todayDay();
        $scheduledClassCount = $classDay === null
            ? 0
            : ClassDutySchedule::where('day', $classDay)->count();
        $classDutyWaiting = ClassDutyReport::where('report_date', $classDate)
            ->where('status', ClassDutyReport::STATUS_MENUNGGU)->count();
        $classDutyFix = ClassDutyReport::where('report_date', $classDate)
            ->where('status', ClassDutyReport::STATUS_PERBAIKI)->count();
        $classDutyDone = ClassDutyReport::where('report_date', $classDate)
            ->where('status', ClassDutyReport::STATUS_DISETUJUI)->count();
        $classDutyMissing = max(0, $scheduledClassCount - ($classDutyWaiting + $classDutyFix + $classDutyDone));
        $classDutyLatest = ClassDutyReport::where('report_date', $classDate)
            ->with(['schoolClass', 'student.userBaru'])
            ->latest()
            ->take(5)
            ->get();

        return view('guru.dashboard', compact(
            'teacher', 'day', 'onDutyDay',
            'waiting', 'waitingCount', 'continued', 'continuedCount',
            'mine', 'mineActive', 'mineTotal', 'notifications', 'unreadCount',
            'serverNow', 'classDate', 'classDay', 'scheduledClassCount',
            'classDutyWaiting', 'classDutyFix', 'classDutyDone', 'classDutyMissing',
            'classDutyLatest'
        ));
    }
}
