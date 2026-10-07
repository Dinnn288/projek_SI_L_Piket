<?php

namespace App\Http\Middleware;

use App\Models\UserBaru;
use App\Support\SingleSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan authenticated user masih valid di users_baru.
 *
 * Laravel SessionGuard memang me-refresh user dari provider pada setiap
 * request, tetapi middleware ini menjadi pengaman terpusat untuk tiga kasus:
 * (B) record akun sudah dihapus, (C) akun masih ada tetapi statusnya
 * nonaktif, (S) sesi lama yang sudah digantikan login baru di device lain
 * (ONE ACCOUNT = ONE ACTIVE SESSION via SingleSession). Ketiganya: logout +
 * invalidate + regenerateToken, lalu redirect ke login dengan pesan yang
 * jelas. Tanpa abort(403) agar tidak stuck di halaman Forbidden. Tidak
 * membuat loop karena route login memakai middleware guest dan middleware
 * ini lolos bila tidak ada user login. Database users_baru adalah sumber
 * kebenaran status akun. Pengecekan single-session hanya berlaku bila ada
 * marker sesi aktif; tanpa marker, perilaku existing dipertahankan (mis.
 * sesi yang dibuat sebelum fitur ini atau via actingAs pada test).
 */
class ValidateAuthenticatedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            // Sesi login yatim: session masih menyimpan ID login tetapi guard
            // me-refresh null karena record users_baru sudah dihapus. Tangani
            // di sini agar pesan tampil (tanpa ini, auth me-redirect polos).
            $staleId = $this->staleLoginId($request);

            if ($staleId !== null) {
                $this->invalidate($request);

                return redirect()
                    ->route('login')
                    ->withErrors(['username' => 'Akun Anda sudah tidak tersedia. Silakan hubungi Admin.']);
            }

            return $next($request);
        }

        $record = UserBaru::where('id', $user->getAuthIdentifier())->first(['id', 'status']);

        if ($record === null) {
            $this->invalidate($request);

            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Akun Anda sudah tidak tersedia. Silakan hubungi Admin.']);
        }

        if ($record->status !== 'aktif') {
            $this->invalidate($request);

            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Akun Anda sedang dinonaktifkan. Silakan hubungi Admin.']);
        }

        // ONE ACCOUNT = ONE ACTIVE SESSION: tolak sesi lama yang sudah
        // digantikan login baru di device lain. Berlaku untuk GET maupun
        // POST/PUT/PATCH/DELETE karena middleware ini ada di grup web.
        // Hanya berlaku bila ada marker sesi aktif; tanpa marker, perilaku
        // existing dipertahankan (mis. sesi via actingAs pada test).
        if (SingleSession::has($record->id)
            && ! SingleSession::isCurrent($record->id, $request->session()->getId())) {
            $this->invalidate($request);

            return redirect()
                ->route('login')
                ->withErrors(['username' => 'Sesi Anda telah berakhir karena akun digunakan untuk login di perangkat lain.']);
        }

        return $next($request);
    }

    private function invalidate(Request $request): void
    {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * Ambil ID login yang tersimpan di session tetapi record-nya sudah
     * tidak ada di users_baru. Mengembalikan null bila tidak ada sesi
     * login yatim (mis. guest biasa atau logout normal yang sudah
     * membersihkan key login dari session).
     */
    private function staleLoginId(Request $request): int|string|null
    {
        foreach ($request->session()->all() as $key => $value) {
            if (! is_string($key) || ! str_starts_with($key, 'login_')) {
                continue;
            }

            if (($value === null || $value === '') || UserBaru::where('id', $value)->exists()) {
                continue;
            }

            return $value;
        }

        return null;
    }
}
