@props(['title' => 'SI-LPiket', 'breadcrumb' => null, 'subtitle' => null, 'sidebar' => null, 'topuser' => null, 'unread' => 0, 'dashboardRoute' => null, 'notificationsRoute' => null])
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
                <div style="display:flex;align-items:center;gap:12px;">
                    <button type="button" class="nav-toggle" data-sl-toggle aria-label="Buka/tutup menu">&#9776;</button>
                    <div>
                        @if ($breadcrumb)<div class="sl-breadcrumb">{{ $breadcrumb }}</div>@endif
                        <div class="sl-title">{{ $title }}</div>
                        @if ($subtitle)<div class="sl-subtitle">{{ $subtitle }}</div>@endif
                    </div>
                </div>
                <div class="sl-topuser">
                    @if ($notificationsRoute ?? $dashboardRoute)
                        <a class="sl-bell" href="{{ $notificationsRoute ?? $dashboardRoute }}" aria-label="Notifikasi">&#128276;@if (($unread ?? 0) > 0)<span class="sl-bell-count">{{ $unread }}</span>@endif</a>
                    @endif
                    <div class="sl-avatar" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()?->name ?? 'G', 0, 1)) }}</div>
                    <div class="who">
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
    <div class="modal-backdrop" id="slLogoutModal" role="dialog" aria-modal="true" aria-labelledby="slLogoutTitle">
        <div class="modal">
            <h3 id="slLogoutTitle">Keluar dari SI-LPiket?</h3>
            <p>Sesi Anda akan diakhiri dan Anda diarahkan ke halaman login.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-sl-logout-cancel>Batal</button>
                <button type="button" class="btn btn-danger" data-sl-logout-confirm>Logout</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var sidebar = document.getElementById('slSidebar');
        document.querySelectorAll('[data-sl-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () { if (sidebar) sidebar.classList.toggle('open'); });
        });
        var pendingForm = null;
        document.querySelectorAll('form.sl-logout').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (!form.dataset.slConfirmed) {
                    e.preventDefault();
                    pendingForm = form;
                    var modal = document.getElementById('slLogoutModal');
                    if (modal) modal.classList.add('open');
                }
            });
        });
        document.querySelectorAll('[data-sl-logout-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = document.getElementById('slLogoutModal');
                if (modal) modal.classList.remove('open');
                pendingForm = null;
            });
        });
        document.querySelectorAll('[data-sl-logout-confirm]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var modal = document.getElementById('slLogoutModal');
                if (modal) modal.classList.remove('open');
                if (pendingForm) {
                    pendingForm.dataset.slConfirmed = '1';
                    pendingForm.submit();
                }
            });
        });
        // Cegah double-submit pada form mutasi (kecuali logout yang
        // memakai modal konfirmasi sendiri) agar tidak ada aksi ganda.
        document.querySelectorAll('form[method="POST"]:not(.sl-logout)').forEach(function (form) {
            form.addEventListener('submit', function () {
                if (form.dataset.slLocked) { event.preventDefault(); return; }
                form.dataset.slLocked = '1';
                var btn = form.querySelector('button[type="submit"]');
                if (btn) { btn.disabled = true; btn.textContent = 'Memproses...'; }
            });
        });
    })();
    </script>
</body>
</html>

