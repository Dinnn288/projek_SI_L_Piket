<x-app-shell :title="$title ?? 'Panel Siswa'" breadcrumb="Siswa" :unread="$headerUnreadCount ?? $unreadCount ?? 0" :dashboardRoute="route('siswa.dashboard')" :notificationsRoute="route('siswa.notifications.index')">
    <x-slot name="sidebar">
        <div class="sl-user">
            <div class="name">{{ auth()->user()?->name ?? 'Siswa' }}</div>
            <div class="meta">{{ $student->schoolClass?->name ?? auth()->user()?->username }} | {{ auth()->user()?->status }}</div>
        </div>
        <nav class="sl-nav">
            <div class="sl-nav-title">Menu</div>
            <a href="{{ route('siswa.dashboard') }}" class="sl-link {{ request()->routeIs('siswa.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('siswa.reports.index') }}" class="sl-link {{ request()->routeIs('siswa.reports.*') && ! request()->routeIs('siswa.reports.create') ? 'active' : '' }}">Laporan Saya</a>
            <a href="{{ route('siswa.reports.create') }}" class="sl-link {{ request()->routeIs('siswa.reports.create') ? 'active' : '' }}">Buat Laporan</a>
            <a href="{{ route('siswa.notifications.index') }}" class="sl-link {{ request()->routeIs('siswa.notifications*') ? 'active' : '' }}">Notifikasi</a>
            <a href="{{ route('siswa.profile.show') }}" class="sl-link {{ request()->routeIs('siswa.profile*') ? 'active' : '' }}">Profil</a>
            <form method="POST" action="{{ route('logout') }}" class="sl-logout">
                @csrf
                <button type="submit" class="btn btn-secondary" style="width:100%;">Logout</button>
            </form>
        </nav>
    </x-slot>
    @yield('content')
</x-app-shell>


