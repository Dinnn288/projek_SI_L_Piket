<?php

namespace App\Http\Controllers;

use App\Models\UserBaru;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = UserBaru::where('username', $credentials['username'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'username' => 'Username atau password salah.',
            ])->onlyInput('username');
        }

        if ($user->status !== 'aktif') {
            return back()->withErrors([
                'username' => 'Akun tidak aktif.',
            ])->onlyInput('username');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended($this->redirectForRole($user->role));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function redirectForRole(?string $role): string
    {
        return match ($role) {
            'admin' => '/admin',
            'siswa' => '/siswa',
            'guru' => '/guru',
            'kepala_sekolah' => '/kepala-sekolah',
            default => '/',
        };
    }
}
