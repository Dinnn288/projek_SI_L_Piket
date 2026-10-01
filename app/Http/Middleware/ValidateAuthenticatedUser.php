<?php

namespace App\Http\Middleware;

use App\Models\UserBaru;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan authenticated user masih tersedia di users_baru.
 *
 * Laravel SessionGuard memang me-refresh user dari provider pada setiap
 * request, tetapi middleware ini menjadi pengaman terpusat bila session
 * berisi ID yang record-nya sudah dihapus: user di-logout, session
 * di-invalidate + token diregenerate, lalu redirect ke login dengan pesan
 * yang tidak membocorkan informasi sensitif. Tidak membuat loop karena
 * route login memakai middleware guest (tidak melewati middleware ini).
 */
class ValidateAuthenticatedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $stillExists = UserBaru::where('id', $user->getAuthIdentifier())->exists();

        if (! $stillExists) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Akun Anda sudah tidak tersedia. Silakan login kembali.']);
        }

        return $next($request);
    }
}
