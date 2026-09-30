<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DutyScheduleController;
use App\Http\Controllers\Admin\MajorController;
use App\Http\Controllers\Admin\ReportCategoryController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Guru\DashboardController as GuruDashboardController;
use App\Http\Controllers\Guru\ReportController as GuruReportController;
use App\Http\Controllers\KepalaSekolah\DashboardController as KepalaSekolahDashboardController;
use App\Http\Controllers\KepalaSekolah\ReportController as KepalaSekolahReportController;
use App\Http\Controllers\Siswa\DashboardController as SiswaDashboardController;
use App\Http\Controllers\Siswa\ReportController as SiswaReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('majors', MajorController::class)->except(['show']);
    Route::resource('classes', SchoolClassController::class)->except(['show']);
    Route::resource('students', StudentController::class)->except(['show']);
    Route::resource('teachers', TeacherController::class)->except(['show']);
    Route::resource('duty-schedules', DutyScheduleController::class)->except(['show']);
    Route::resource('report-categories', ReportCategoryController::class)->except(['show']);
});

// Existing role-protected routes for testing
Route::middleware('auth')->group(function () {
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/test-admin', fn () => view('dashboard.area', ['title' => 'Area Admin Test']));
    });

    Route::middleware('role:siswa')->prefix('siswa')->name('siswa.')->group(function () {
        Route::get('/', [SiswaDashboardController::class, 'index'])->name('dashboard');
        Route::get('/laporan', [SiswaReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/create', [SiswaReportController::class, 'create'])->name('reports.create');
        Route::post('/laporan', [SiswaReportController::class, 'store'])->name('reports.store');
        Route::get('/laporan/{report}', [SiswaReportController::class, 'show'])->name('reports.show');
        Route::get('/laporan/{report}/attachment/{attachment}', [SiswaReportController::class, 'attachment'])->name('reports.attachment');
    });

    Route::middleware('role:guru')->prefix('guru')->name('guru.')->group(function () {
        Route::get('/', [GuruDashboardController::class, 'index'])->name('dashboard');
        Route::get('/laporan', [GuruReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/{report}', [GuruReportController::class, 'show'])->name('reports.show');
        Route::get('/laporan/{report}/attachment/{attachment}', [GuruReportController::class, 'attachment'])->name('reports.attachment');
        Route::post('/laporan/{report}/claim', [GuruReportController::class, 'claim'])->name('reports.claim');
        Route::post('/laporan/{report}/takeover', [GuruReportController::class, 'takeover'])->name('reports.takeover');
        Route::post('/laporan/{report}/complete', [GuruReportController::class, 'complete'])->name('reports.complete');
        Route::post('/laporan/{report}/forward', [GuruReportController::class, 'forward'])->name('reports.forward');
    });

    Route::middleware('role:kepala_sekolah')->prefix('kepala-sekolah')->name('kepala-sekolah.')->group(function () {
        Route::get('/', [KepalaSekolahDashboardController::class, 'index'])->name('dashboard');
        Route::get('/laporan', [KepalaSekolahReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/{report}', [KepalaSekolahReportController::class, 'show'])->name('reports.show');
        Route::get('/laporan/{report}/attachment/{attachment}', [KepalaSekolahReportController::class, 'attachment'])->name('reports.attachment');
        Route::post('/laporan/{report}/complete', [KepalaSekolahReportController::class, 'complete'])->name('reports.complete');
        Route::post('/laporan/{report}/return', [KepalaSekolahReportController::class, 'returnToTeacher'])->name('reports.return');
    });
});