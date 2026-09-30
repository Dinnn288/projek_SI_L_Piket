<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin SI-LPiket' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f6fa;
            min-height: 100vh;
        }
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 260px;
            background: #2c3e50;
            color: #ecf0f1;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #34495e;
        }
        .sidebar-header h1 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .sidebar-header small {
            color: #95a5a6;
            font-size: 12px;
        }
        .user-info {
            padding: 15px 20px;
            border-bottom: 1px solid #34495e;
            background: #34495e;
        }
        .user-info .name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 3px;
        }
        .user-info .role {
            font-size: 12px;
            color: #95a5a6;
            text-transform: capitalize;
        }
        .nav-menu {
            padding: 15px 0;
        }
        .nav-section {
            margin-bottom: 20px;
        }
        .nav-section-title {
            padding: 0 20px 8px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7f8c8d;
            font-weight: 600;
        }
        .nav-link {
            display: block;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .nav-link:hover,
        .nav-link.active {
            background: #34495e;
            color: #ffffff;
            border-left-color: #3498db;
        }
        .nav-link i {
            margin-right: 10px;
            width: 18px;
            text-align: center;
        }
        .main-content {
            flex: 1;
            margin-left: 260px;
            min-height: 100vh;
        }
        .topbar {
            background: #ffffff;
            border-bottom: 1px solid #e1e8ed;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar h2 {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .user-dropdown {
            position: relative;
        }
        .user-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: #f5f6fa;
            border: 1px solid #e1e8ed;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            color: #2c3e50;
        }
        .user-btn:hover {
            background: #e1e8ed;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        .user-name {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .user-role {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: capitalize;
        }
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            min-width: 200px;
            display: none;
            z-index: 1000;
        }
        .dropdown-menu.show {
            display: block;
        }
        .dropdown-item {
            display: block;
            padding: 12px 15px;
            color: #2c3e50;
            text-decoration: none;
            font-size: 13px;
            transition: background 0.2s;
        }
        .dropdown-item:hover {
            background: #f5f6fa;
        }
        .dropdown-divider {
            border-top: 1px solid #e1e8ed;
            margin: 5px 0;
        }
        .logout-form {
            margin: 0;
        }
        .logout-btn {
            width: 100%;
            text-align: left;
            padding: 12px 15px;
            background: none;
            border: none;
            color: #e74c3c;
            font-size: 13px;
            cursor: pointer;
            text-align: left;
        }
        .logout-btn:hover {
            background: #fef2f2;
        }
        .content-area {
            padding: 30px;
        }
        .welcome-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 40px;
            max-width: 800px;
        }
        .welcome-card h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .welcome-card p {
            color: #7f8c8d;
            font-size: 16px;
            line-height: 1.6;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 25px;
            border-left: 4px solid #3498db;
        }
        .stat-card h3 {
            font-size: 13px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }
        .stat-card.success { border-left-color: #27ae60; }
        .stat-card.warning { border-left-color: #f39c12; }
        .stat-card.danger { border-left-color: #e74c3c; }
        .stat-card.info { border-left-color: #3498db; }
        .nav-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 200;
            background: #2c3e50;
            color: white;
            border: none;
            padding: 10px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .nav-toggle {
                display: block;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 99;
            }
            .sidebar-overlay.open {
                display: block;
            }
            .topbar {
                padding-left: 55px;
            }
        }
        .flash-message {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .flash-success {
            background: #e8f8f5;
            border: 1px solid #27ae60;
            color: #27ae60;
        }
        .flash-error {
            background: #fef2f2;
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }
        .close-btn {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }
        .close-btn:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <button class="nav-toggle" onclick="toggleSidebar()" aria-label="Toggle navigation">
            &#9776;
        </button>
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h1>SI-LPIKET</h1>
                <small>Sistem Informasi Laporan Piket</small>
            </div>

<div class="user-info">
                <div class="name">{{ auth()->user()?->name ?? 'Guest' }}</div>
                <div class="role">{{ auth()->user()?->role ?? 'guest' }}</div>
            </div>

            <nav class="nav-menu">
                <div class="nav-section">
                    <div class="nav-section-title">Dashboard</div>
                    <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Data Sistem</div>
                    <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                        <span>Users</span>
                    </a>
                    <a href="{{ route('admin.majors.index') }}" class="nav-link {{ request()->routeIs('admin.majors*') ? 'active' : '' }}">
                        <span>Jurusan</span>
                    </a>
                    <a href="{{ route('admin.classes.index') }}" class="nav-link {{ request()->routeIs('admin.classes*') ? 'active' : '' }}">
                        <span>Kelas</span>
                    </a>
                    <a href="{{ route('admin.students.index') }}" class="nav-link {{ request()->routeIs('admin.students*') ? 'active' : '' }}">
                        <span>Siswa</span>
                    </a>
                    <a href="{{ route('admin.teachers.index') }}" class="nav-link {{ request()->routeIs('admin.teachers*') ? 'active' : '' }}">
                        <span>Guru</span>
                    </a>
                    <a href="{{ route('admin.duty-schedules.index') }}" class="nav-link {{ request()->routeIs('admin.duty-schedules*') ? 'active' : '' }}">
                        <span>Jadwal Guru Piket</span>
                    </a>
                    <a href="{{ route('admin.report-categories.index') }}" class="nav-link {{ request()->routeIs('admin.report-categories*') ? 'active' : '' }}">
                        <span>Kategori & Prioritas</span>
                    </a>
                </div>
            </nav>
        </aside>

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div class="main-content">
            <header class="topbar">
                <button class="nav-toggle" onclick="toggleSidebar()" aria-label="Toggle navigation">
                    &#9776;
                </button>
                <h2>{{ $title ?? 'Admin SI-LPiket' }}</h2>
                <div class="topbar-right">
                    <div class="user-dropdown">
                        <button class="user-btn" onclick="toggleDropdown()" aria-expanded="false" aria-haspopup="true">
                            <div class="user-avatar">{{ strtoupper(auth()->user()?->name[0] ?? 'G') }}</div>
                            <div>
                                <div class="user-name">{{ auth()->user()?->name ?? 'Guest' }}</div>
                                <div class="user-role">{{ auth()->user()?->role ?? 'guest' }}</div>
                            </div>
                        </button>
                        <div class="dropdown-menu" id="userDropdown" role="menu">
                            <div class="dropdown-item" style="padding: 12px 15px; font-weight: 600; color: #2c3e50;">{{ auth()->user()?->name ?? 'Guest' }}</div>
                            <div class="dropdown-item" style="font-size: 12px; color: #7f8c8d;">{{ auth()->user()?->role ?? 'guest' }} | {{ auth()->user()?->status ?? '' }}</div>
                            <div class="dropdown-divider"></div>
                            @auth
                            <form method="POST" action="{{ route('logout') }}" class="logout-form">
                                @csrf
                                <button type="submit" class="logout-btn" role="menuitem">Logout</button>
                            </form>
                            @endauth
                        </div>
                    </div>
                </div>
            </header>

            <main class="content-area">
                @if (session('success'))
                    <div class="flash-message flash-success">
                        {{ session('success') }}
                        <button type="button" class="close-btn" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="flash-message flash-error">
                        {{ session('error') }}
                        <button type="button" class="close-btn" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                @endif
                @if (isset($errors) && $errors->any())
                    <div class="flash-message flash-error">
                        <ul style="margin: 0; padding-left: 20px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        }

        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const btn = document.querySelector('.user-btn');
            if (dropdown && dropdown.classList.contains('show') && !dropdown.contains(e.target) && !btn.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                const dropdown = document.getElementById('userDropdown');
                sidebar?.classList.remove('open');
                overlay?.classList.remove('open');
                dropdown?.classList.remove('show');
            }
        });
    </script>
</body>
</html>