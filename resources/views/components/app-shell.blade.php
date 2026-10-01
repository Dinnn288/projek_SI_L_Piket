@props(['title' => 'SI-LPiket', 'breadcrumb' => null, 'subtitle' => null, 'sidebar' => null, 'topuser' => null, 'unread' => 0, 'dashboardRoute' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="stylesheet" href="{{ asset('css/si-lpiket.css') }}">
</head>
<body>
    <div class="sl-layout">
        <aside class="sl-sidebar" id="slSidebar">
            <div class="sl-brand">
                <h1>SI-LPiket</h1>
                <small>Sistem Informasi Laporan Piket</small>
            </div>
            {{ $sidebar }}
        </aside>
        <div class="sl-main">
            <header class="sl-topbar">
                <div>
                    @if ($breadcrumb)<div class="sl-breadcrumb">{{ $breadcrumb }}</div>@endif
                    <div class="sl-title">{{ $title }}</div>
                    @if ($subtitle)<div class="sl-subtitle">{{ $subtitle }}</div>@endif
                </div>
                <div class="sl-topuser">
                    @if ($dashboardRoute)
                        <a class="sl-bell" href="{{ $dashboardRoute }}" aria-label="Notifikasi">&#128276;@if (($unread ?? 0) > 0)<span class="sl-bell-count">{{ $unread }}</span>@endif</a>
                    @endif
                    <div class="sl-avatar">{{ strtoupper(mb_substr(auth()->user()?->name ?? 'G', 0, 1)) }}</div>
                    <div>
                        <div style="font-weight:700;">{{ auth()->user()?->name ?? 'Guest' }}</div>
                        <div style="font-size:12px;color:var(--muted);text-transform:capitalize;">{{ auth()->user()?->role ?? 'guest' }}</div>
                    </div>
                </div>
            </header>
            <main class="sl-content">
                @if (session('success'))
                    <div class="flash flash-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="flash flash-error">{{ session('error') }}</div>
                @endif
                @if (isset($errors) && $errors->any())
                    <div class="flash flash-error">
                        <ul style="margin:0;padding-left:20px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>

