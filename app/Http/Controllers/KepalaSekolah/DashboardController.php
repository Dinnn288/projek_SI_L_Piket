<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $forwardedQuery = Report::where('status', 'diteruskan')
            ->with(['category', 'schoolClass']);

        $forwardedCount = (clone $forwardedQuery)->count();
        $completedCount = Report::where('status', 'selesai')->count();
        $latest = (clone $forwardedQuery)
            ->with(['student.userBaru', 'assignedTeacher.userBaru'])
            ->latest()
            ->take(10)
            ->get();

        $notifications = Notification::where('user_id', $request->user()->id)
            ->with('report')
            ->latest()
            ->limit(3)
            ->get();
        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return view('kepala-sekolah.dashboard', compact(
            'forwardedCount', 'completedCount', 'latest', 'notifications', 'unreadCount'
        ));
    }

    /**
     * Data monitoring ringan: laporan dalam_penanganan beserta guru yang
     * mengambilnya. Dipakai polling dashboard agar Kepsek melihat perubahan
     * claim Guru tanpa full reload. Scope sama dengan halaman monitoring.
     */
    public function monitoringJson(): JsonResponse
    {
        $reports = Report::where('status', 'dalam_penanganan')
            ->with(['student.userBaru:id,name', 'schoolClass:id,name', 'assignedTeacher.userBaru:id,name'])
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'title' => $report->title,
                'student' => $report->student?->userBaru?->name,
                'class' => $report->schoolClass?->name,
                'location' => $report->location,
                'teacher' => $report->assignedTeacher?->userBaru?->name,
                'assigned_at' => optional($report->assigned_at)->format('d M Y H:i'),
                'status' => $report->status,
                'detail_url' => route('kepala-sekolah.reports.show', $report),
            ]);

        return response()->json([
            'server_time' => now()->format('d M Y H:i:s'),
            'count' => $reports->count(),
            'reports' => $reports,
        ]);
    }
}
