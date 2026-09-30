<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Report;
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
            ->take(5)
            ->get();
        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return view('kepala-sekolah.dashboard', compact(
            'forwardedCount', 'completedCount', 'latest', 'notifications', 'unreadCount'
        ));
    }
}
