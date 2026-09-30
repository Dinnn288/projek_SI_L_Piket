<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Kepala Sekolah SI-LPiket' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f6fa; min-height: 100vh; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #4a235a; color: #fff; height: 100vh; position: fixed; left: 0; top: 0; overflow-y: auto; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,.2); }
        .sidebar-header h1 { font-size: 18px; margin-bottom: 4px; }
        .sidebar-header small { font-size: 12px; opacity: .8; }
        .user-info { padding: 14px 20px; background: rgba(0,0,0,.15); border-bottom: 1px solid rgba(255,255,255,.2); }
        .user-info .name { font-weight: 600; font-size: 14px; }
        .nav-menu { padding: 14px 0; }
        .nav-link { display: block; padding: 11px 20px; color: #f5eef8; text-decoration: none; font-size: 14px; border-left: 3px solid transparent; }
        .nav-link:hover, .nav-link.active { background: rgba(0,0,0,.2); color: #fff; border-left-color: #f9e79f; }
        .main { flex: 1; margin-left: 250px; min-height: 100vh; }
        .topbar { background: #fff; border-bottom: 1px solid #e1e8ed; padding: 14px 28px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50; }
        .topbar h2 { font-size: 19px; color: #4a235a; }
        .content { padding: 28px; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 28px; }
        .btn { display: inline-block; background: #4a235a; color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: 600; border: none; cursor: pointer; }
        .btn-secondary { background: #7f8c8d; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th { background: #f5f6fa; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed; font-size: 14px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-forward { background: #fef9e7; color: #7e5109; border: 1px solid #f9e79f; }
        .badge-done { background: #eafaf1; color: #1e8449; border: 1px solid #a9dfbf; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-top: 22px; }
        .stat { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 20px; border-left: 4px solid #4a235a; }
        .stat h3 { font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 6px; }
        .stat .value { font-size: 26px; font-weight: 700; color: #4a235a; }
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-top: 10px; }
        .photo-grid img { width: 100%; height: 140px; object-fit: cover; border-radius: 6px; border: 1px solid #e1e8ed; }
        .photo-grid .caption { font-size: 11px; color: #5d6d7e; margin-top: 4px; word-break: break-all; }
        .table-wrap { overflow-x: auto; }
        @media (max-width: 768px) {
            .sidebar { width: 100%; height: auto; position: static; }
            .main { margin-left: 0; }
            .content { padding: 16px; }
            .card { padding: 18px; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>SI-LPiket</h1>
                <small>Panel Kepala Sekolah</small>
            </div>
            <div class="user-info">
                <div class="name">{{ auth()->user()?->name ?? 'Kepala Sekolah' }}</div>
            </div>
            <nav class="nav-menu">
                <a href="{{ route('kepala-sekolah.dashboard') }}" class="nav-link {{ request()->routeIs('kepala-sekolah.dashboard') ? 'active' : '' }}"><span>Dashboard @if (($unreadCount ?? 0) > 0) ({{ $unreadCount }} baru) @endif</span></a>
                <a href="{{ route('kepala-sekolah.reports.index') }}" class="nav-link {{ request()->routeIs('kepala-sekolah.reports.*') ? 'active' : '' }}"><span>Laporan Diteruskan</span></a>
                <form method="POST" action="{{ route('logout') }}" style="margin: 12px 20px 0;">
                    @csrf
                    <button type="submit" class="btn btn-secondary" style="width: 100%;">Logout</button>
                </form>
            </nav>
        </aside>
        <div class="main">
            <header class="topbar">
                <h2>{{ $title ?? 'Panel Kepala Sekolah' }}</h2>
                <div style="font-size: 13px; color: #5d6d7e;">{{ auth()->user()?->name }} (kepala_sekolah)</div>
            </header>
            <main class="content">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
