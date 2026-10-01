<x-app-shell :title="$title ?? 'Panel Kepala Sekolah'" breadcrumb="Kepala Sekolah" :unread="$unreadCount ?? 0" :dashboardRoute="route('kepala-sekolah.dashboard')">
    <x-slot name="sidebar">
        <div class="sl-user">
            <div class="name">{{ auth()->user()?->name ?? 'Kepala Sekolah' }}</div>
            <div class="meta">kepala_sekolah</div>
        </div>
        <nav class="sl-nav">
            <div class="sl-nav-title">Menu</div>
            <a href="{{ route('kepala-sekolah.dashboard') }}" class="sl-link {{ request()->routeIs('kepala-sekolah.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('kepala-sekolah.reports.index') }}" class="sl-link {{ request()->routeIs('kepala-sekolah.reports.*') ? 'active' : '' }}">Laporan Diteruskan</a>
            <a href="{{ route('kepala-sekolah.profile.show') }}" class="sl-link {{ request()->routeIs('kepala-sekolah.profile*') ? 'active' : '' }}">Profil</a>
            <form method="POST" action="{{ route('logout') }}" class="sl-logout">
                @csrf
                <button type="submit" class="btn btn-secondary" style="width:100%;">Logout</button>
            </form>
        </nav>
    </x-slot>
    @yield('content')
</x-app-shell>

