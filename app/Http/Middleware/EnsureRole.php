<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Batasi akses berdasarkan role users_baru.
     * Contoh: ->middleware('role:admin'), ->middleware('role:guru,kepala_sekolah').
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $role = $user?->role;

        if (! is_string($role) || ! in_array($role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
