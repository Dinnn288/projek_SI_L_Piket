<x-app-shell :title="$title ?? 'Admin SI-LPiket'" breadcrumb="Admin" :unread="$headerUnreadCount ?? $unreadCount ?? 0" :dashboardRoute="route('admin.dashboard')" :notificationsRoute="route('admin.notifications.index')">
    <x-slot name="sidebar">
        <div class="sl-user">
            <div class="name">{{ auth()->user()?->name ?? 'Guest' }}</div>
            <div class="meta">{{ auth()->user()?->role ?? 'guest' }}</div>
        </div>
        <nav class="sl-nav">
            <div class="sl-nav-title">Dashboard</div>
            <a href="{{ route('admin.dashboard') }}" class="sl-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('admin.profile.show') }}" class="sl-link {{ request()->routeIs('admin.profile*') ? 'active' : '' }}">Profil</a>
            <div class="sl-nav-title">Data Sistem</div>
            <a href="{{ route('admin.users.index') }}" class="sl-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">Users</a>
            <a href="{{ route('admin.majors.index') }}" class="sl-link {{ request()->routeIs('admin.majors*') ? 'active' : '' }}">Jurusan</a>
            <a href="{{ route('admin.classes.index') }}" class="sl-link {{ request()->routeIs('admin.classes*') ? 'active' : '' }}">Kelas</a>
            <a href="{{ route('admin.students.index') }}" class="sl-link {{ request()->routeIs('admin.students*') ? 'active' : '' }}">Siswa</a>
            <a href="{{ route('admin.teachers.index') }}" class="sl-link {{ request()->routeIs('admin.teachers*') ? 'active' : '' }}">Guru</a>
            <a href="{{ route('admin.duty-schedules.index') }}" class="sl-link {{ request()->routeIs('admin.duty-schedules*') ? 'active' : '' }}">Jadwal Guru Piket</a>
            <a href="{{ route('admin.report-categories.index') }}" class="sl-link {{ request()->routeIs('admin.report-categories*') ? 'active' : '' }}">Kategori &amp; Prioritas</a>
            <a href="{{ route('admin.notifications.index') }}" class="sl-link {{ request()->routeIs('admin.notifications*') ? 'active' : '' }}">Notifikasi</a>
            <form method="POST" action="{{ route('logout') }}" class="sl-logout">
                @csrf
                <button type="submit" class="btn btn-secondary" style="width:100%;">Logout</button>
            </form>
        </nav>
    </x-slot>
    @yield('content')
</x-app-shell>


