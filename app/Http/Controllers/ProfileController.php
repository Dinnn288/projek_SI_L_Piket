<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $user->password = Hash::make($data['password']);
        $user->save();

        return back()->with('success', 'Password berhasil diubah.');
    }
}
