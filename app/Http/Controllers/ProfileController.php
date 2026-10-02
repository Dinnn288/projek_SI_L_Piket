<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\UserBaru;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $view = match ($user->role) {
            'admin' => 'profile.admin',
            'siswa' => 'profile.siswa',
            'guru' => 'profile.guru',
            'kepala_sekolah' => 'profile.kepala-sekolah',
            default => abort(403),
        };

        return view($view, ['profileUser' => $user]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.different' => 'Password baru tidak boleh sama dengan password saat ini.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()
                ->withErrors(['current_password' => 'Password saat ini tidak sesuai.'])
                ->withInput();
        }

        DB::transaction(function () use ($user, $data) {
            $user->password = Hash::make($data['password']);
            $user->save();

            // Notifikasi audit ke seluruh Admin aktif — hanya setelah
            // password benar-benar tersimpan (dalam transaction yang sama).
            $changedAt = Carbon::now(config('app.timezone'));
            $when = $changedAt->translatedFormat('j F Y').' pukul '.$changedAt->format('H:i');
            $adminIds = UserBaru::where('role', 'admin')
                ->where('status', 'aktif')
                ->pluck('id');

            foreach ($adminIds as $adminId) {
                $exists = Notification::where('user_id', $adminId)
                    ->where('title', 'Perubahan Password')
                    ->where('message', 'like', 'Akun '.$user->name.' mengubah password pada '.$when.'.%')
                    ->exists();

                if (! $exists) {
                    Notification::create([
                        'user_id' => $adminId,
                        'report_id' => null,
                        'title' => 'Perubahan Password',
                        'message' => 'Akun '.$user->name.' mengubah password pada '.$when.'.',
                        'is_read' => false,
                    ]);
                }
            }
        });

        return back()->with('success', 'Password berhasil diubah.');
    }
}
