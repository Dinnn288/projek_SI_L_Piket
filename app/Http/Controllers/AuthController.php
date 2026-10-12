<?php

namespace App\Http\Controllers;

use App\Models\UserBaru;
use App\Support\SingleSession;
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

        // FIRST LOGIN WINS: klaim atomik — tolak login baru selama masih
        // ada sesi aktif untuk akun ini. Marker TIDAK di-overwrite agar
        // Device A tetap login; Device B tetap di halaman login dengan
        // pesan yang jelas. Klaim dilakukan SETELAH regenerate agar marker
        // menyimpan session ID final. Bila klaim gagal, Auth::login tidak
        // pernah dipanggil sehingga tidak ada sesi setengah jadi.
        Auth::login($user);
        $request->session()->regenerate();
        $sessionId = $request->session()->getId();

        $claim = SingleSession::tryClaim($user->id, $sessionId);

        if (! $claim['claimed']) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'username' => 'Akun sedang digunakan pada perangkat lain. Silakan logout dari perangkat tersebut terlebih dahulu.',
            ])->onlyInput('username');
        }

        return redirect()->intended($this->redirectForRole($user->role));
    }

    public function logout(Request $request): RedirectResponse
    {
        $userId = Auth::id();
        $sessionId = $request->session()->getId();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Logout normal membebaskan akun: hapus marker sesi aktif milik
        // sesi ini sehingga device lain boleh login kembali.
        if ($userId !== null) {
            SingleSession::forget($userId, $sessionId);
        }

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
