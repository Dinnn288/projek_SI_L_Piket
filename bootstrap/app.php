<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Percayai forwarded headers dari reverse proxy lokal (mis. cloudflared
        // Cloudflare Tunnel yang berjalan di localhost) agar Laravel mengenali
        // skema HTTPS publik via X-Forwarded-Proto. Hanya IP loopback yang
        // dipercaya — bukan '*' — sehingga header dari sumber luar tidak bisa
        // memalsukan skema. Tanpa ini asset() menghasilkan URL http:// yang
        // diblokir browser sebagai Mixed Content pada halaman https://.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\ValidateAuthenticatedUser::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
