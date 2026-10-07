<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Report;
use App\Models\Teacher;
use App\Services\GuruDutyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private function currentTeacher(Request $request): Teacher
    {
        return Teacher::resolveForUser($request->user());
    }

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);
        $day = GuruDutyService::todayDay();
        $onDutyDay = GuruDutyService::isOnDutyDay($teacher);
        $withinHours = GuruDutyService::isWithinHours();

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

        $operationalStart = GuruDutyService::start();
        $operationalEnd = GuruDutyService::end();
        $serverNow = GuruDutyService::now();

        return view('guru.dashboard', compact(
            'teacher', 'day', 'onDutyDay', 'withinHours',
            'waiting', 'waitingCount', 'continued', 'continuedCount',
            'mine', 'mineActive', 'mineTotal', 'notifications', 'unreadCount',
            'operationalStart', 'operationalEnd', 'serverNow'
        ));
    }
}
