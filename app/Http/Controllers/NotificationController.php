<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Tujuan redirect per notifikasi berdasarkan role pemilik.
     */
    public static function destinationFor(Request $request, Notification $notification): string
    {
        $role = $request->user()->role;

        if ($notification->report_id === null) {
            // Notifikasi piket kelas (tanpa report_id): arahkan ke halaman
            // piket kelas masing-masing role bila judulnya cocok.
            $title = (string) ($notification->title ?? '');

            if (str_starts_with($title, 'Laporan Piket')) {
                return match ($role) {
                    'siswa' => route('siswa.class-duty.index'),
                    'guru' => route('guru.class-duty.index'),
                    'admin' => route('admin.dashboard'),
                    'kepala_sekolah' => route('kepala-sekolah.dashboard'),
                    default => route('login'),
                };
            }

            return match ($role) {
                'admin' => route('admin.dashboard'),
                'siswa' => route('siswa.dashboard'),
                'guru' => route('guru.dashboard'),
                'kepala_sekolah' => route('kepala-sekolah.dashboard'),
                default => route('login'),
            };
        }

        return match ($role) {
            'admin' => route('admin.dashboard'),
            'siswa' => route('siswa.reports.show', $notification->report_id),
            'guru' => route('guru.reports.show', $notification->report_id),
            'kepala_sekolah' => route('kepala-sekolah.reports.show', $notification->report_id),
            default => route('login'),
        };
    }

    private function viewFor(string $role): string
    {
        return match ($role) {
            'admin' => 'notifications.admin',
            'siswa' => 'notifications.siswa',
            'guru' => 'notifications.guru',
            'kepala_sekolah' => 'notifications.kepala-sekolah',
            default => abort(403),
        };
    }

    public function index(Request $request): View
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->with('report')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view($this->viewFor($request->user()->role), compact('notifications'));
    }

    public function read(Request $request, Notification $notification): RedirectResponse
    {
        if ((int) $notification->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        if (! $notification->is_read) {
            $notification->is_read = true;
            $notification->save();
        }

        return redirect()->to(static::destinationFor($request, $notification->fresh()));
    }
}
