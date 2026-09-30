<?php

namespace App\Http\Controllers\Admin;

use App\Models\UserBaru;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Major;
use App\Models\ReportCategory;
use App\Models\DutySchedule;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'users_baru' => UserBaru::count(),
            'students' => Student::count(),
            'teachers' => Teacher::count(),
            'classes' => SchoolClass::count(),
            'majors' => Major::count(),
            'categories' => ReportCategory::count(),
            'schedules' => DutySchedule::count(),
        ];

        return view('dashboard.admin', compact('stats'));
    }
}