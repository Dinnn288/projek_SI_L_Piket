<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Student;
use App\Services\ClassDutyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private function currentStudent(Request $request): Student
    {
        $student = $request->user()->student()->with('schoolClass')->first();

        if (! $student || ! $student->class_id) {
            abort(403, 'Data siswa tidak ditemukan.');
        }

        return $student;
    }

    public function index(Request $request): View
    {
        $student = $this->currentStudent($request);

        $reportsQuery = $student->reports()->with(['category'])->latest();

        $total = (clone $reportsQuery)->count();
        $menunggu = (clone $reportsQuery)->where('status', 'menunggu')->count();
        $diproses = (clone $reportsQuery)->whereIn('status', ['dalam_penanganan', 'diteruskan'])->count();
        $selesai = (clone $reportsQuery)->where('status', 'selesai')->count();
        $latest = (clone $reportsQuery)->with('attachments')->take(5)->get();

        $notifications = Notification::where('user_id', $request->user()->id)
            ->with('report')
            ->latest()
            ->limit(3)
            ->get();
        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        $classDay = ClassDutyService::todayDay();
        $classDate = ClassDutyService::todayDate();
        $classScheduled = ClassDutyService::isScheduled((int) $student->class_id, $classDay);
        $classStatus = ClassDutyService::statusFor((int) $student->class_id, $classDate, $classDay);
        $classReport = ClassDutyService::reportFor((int) $student->class_id, $classDate);

        return view('siswa.dashboard', compact('student', 'total', 'menunggu', 'diproses', 'selesai', 'latest', 'notifications', 'unreadCount', 'classDay', 'classDate', 'classScheduled', 'classStatus', 'classReport'));
    }
}
