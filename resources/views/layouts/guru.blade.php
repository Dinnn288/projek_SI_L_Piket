<x-app-shell :title="$title ?? 'Panel Guru Piket'" breadcrumb="Guru Piket" :unread="$headerUnreadCount ?? $unreadCount ?? 0" :dashboardRoute="route('guru.dashboard')" :notificationsRoute="route('guru.notifications.index')" :profileRoute="route('guru.profile.show')">
    <x-slot name="sidebar">
        <div class="sl-user">
            <div class="name">{{ auth()->user()?->name ?? 'Guru' }}</div>
            <div class="meta">NIP: {{ $teacher?->nip ?? '-' }} | Hari ini: {{ $day ?? '-' }}</div>
        </div>
        <nav class="sl-nav">
            <div class="sl-nav-title">Menu</div>
            <a href="{{ route('guru.dashboard') }}" class="sl-link {{ request()->routeIs('guru.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('guru.class-duty.index') }}" class="sl-link {{ request()->routeIs('guru.class-duty*') ? 'active' : '' }}">Piket Kelas</a>
            <a href="{{ route('guru.reports.index') }}" class="sl-link {{ request()->routeIs('guru.reports.*') ? 'active' : '' }}">Laporan Piket</a>
            <form method="POST" action="{{ route('logout') }}" class="sl-logout">
                @csrf
                <button type="submit" class="btn btn-secondary" style="width:100%;">Logout</button>
            </form>
        </nav>
    </x-slot>
    @yield('content')
</x-app-shell>


