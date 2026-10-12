<?php

namespace App\Providers;

use App\Models\Notification;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\GuruDutyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Badge header dinamis: sediakan $headerUnreadCount (milik user
        // login) untuk seluruh view tanpa menimpa $unreadCount yang sudah
        // dikirim controller existing.
        View::composer('*', function ($view) {
            if (! array_key_exists('headerUnreadCount', $view->getData()) && Auth::check()) {
                $view->with('headerUnreadCount', Notification::where('user_id', Auth::id())
                    ->where('is_read', false)
                    ->count());
            }
        });

        // Context sidebar Guru: sediakan $teacher (milik user login,
        // idempoten via resolveForUser) dan $day (WIB via GuruDutyService)
        // untuk layout guru bila controller tidak mengirimnya (misal
        // halaman Notifikasi dan Profil generik). Tidak menimpa nilai
        // yang sudah dikirim controller existing.
        View::composer('layouts.guru', function ($view) {
            $data = $view->getData();

            if (Auth::check() && ($data['__guruResolved'] ?? false) !== true) {
                $user = Auth::user();

                if ($user && $user->role === 'guru') {
                    if (! array_key_exists('teacher', $data) || $data['teacher'] === null) {
                        // B2.1: jangan membuat profil di composer; profil
                        // yatim dibiarkan null dan ditangani controller.
                        $view->with('teacher', $user->teacher()->first());
                    }

                    if (! array_key_exists('day', $data) || $data['day'] === null) {
                        $view->with('day', GuruDutyService::todayDay());
                    }
                }

                $view->with('__guruResolved', true);
            }
        });

        // Context sidebar Siswa: sediakan $student (milik user login +
        // relasi kelas) untuk layout siswa bila controller tidak
        // mengirimnya (misal halaman Notifikasi dan Profil generik).
        // Tidak menimpa nilai yang sudah dikirim controller existing.
        View::composer('layouts.siswa', function ($view) {
            $data = $view->getData();

            if (Auth::check() && ($data['__siswaResolved'] ?? false) !== true) {
                $user = Auth::user();

                if ($user && $user->role === 'siswa') {
                    if (! array_key_exists('student', $data) || $data['student'] === null) {
                        $student = Student::where('user_id', $user->id)->with('schoolClass')->first();

                        if ($student) {
                            $view->with('student', $student);
                        }
                    }
                }

                $view->with('__siswaResolved', true);
            }
        });
    }
}
