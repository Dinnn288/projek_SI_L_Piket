@props(['profileRoute' => null, 'notificationsRoute' => null, 'unread' => 0])
<div class="sl-profile-wrap" data-sl-profile>
    <button type="button" class="sl-profile-btn" aria-haspopup="true" aria-expanded="false" aria-label="Menu profil">
        <div class="sl-avatar" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()?->name ?? 'G', 0, 1)) }}</div>
        <div class="who">
            <div style="font-weight:700;">{{ auth()->user()?->name ?? 'Guest' }}</div>
            <div style="font-size:12px;color:var(--muted);text-transform:capitalize;">{{ auth()->user()?->role ?? 'guest' }}</div>
        </div>
        <span class="caret" aria-hidden="true">▼</span>
    </button>
    <div class="sl-profile-menu" role="menu">
        @if ($profileRoute)
            <a href="{{ $profileRoute }}" class="sl-menu-item {{ request()->fullUrlIs($profileRoute . '*') ? 'active' : '' }}" role="menuitem"><span class="sl-menu-ico" aria-hidden="true">👤</span>Profil</a>
        @endif
        @if ($notificationsRoute)
            <a href="{{ $notificationsRoute }}" class="sl-menu-item {{ request()->fullUrlIs($notificationsRoute . '*') ? 'active' : '' }}" role="menuitem"><span class="sl-menu-ico" aria-hidden="true">🔔</span>Notifikasi
                @if (($unread ?? 0) > 0)
                    <span class="badge badge-proses">{{ $unread }}</span>
                @endif
            </a>
        @endif
        <hr class="sl-menu-sep">
        <form method="POST" action="{{ route('logout') }}" class="sl-logout" style="margin:0;">
            @csrf
            <button type="submit" class="sl-menu-item danger" role="menuitem"><span class="sl-menu-ico" aria-hidden="true">🚪</span>Logout</button>
        </form>
    </div>
</div>
