@props(['title' => 'SI-LPiket', 'breadcrumb' => null, 'subtitle' => null, 'sidebar' => null, 'topuser' => null, 'unread' => 0, 'dashboardRoute' => null, 'notificationsRoute' => null, 'profileRoute' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/si-lpiket.css') }}">
</head>
<body>
    <div class="sl-layout">
        <div class="sl-backdrop" id="slBackdrop" aria-hidden="true"></div>
        <aside class="sl-sidebar" id="slSidebar">
            <div class="sl-brand">
                <div class="sl-brand-logo">
                    <img src="{{ asset('images/Logo-si-lpiket.jpg') }}" alt="Logo SI-LPiket" width="44" height="44">
                    <div class="sl-brand-text">
                        <h1>SI-LPiket</h1>
                        <small>Sistem Informasi Laporan Piket</small>
                    </div>
                </div>
                <button type="button" class="sl-sidebar-close" data-sl-close aria-label="Tutup menu">&times;</button>
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
                    <x-profile-dropdown :profileRoute="$profileRoute ?? null" :notificationsRoute="$notificationsRoute ?? null" :unread="$unread ?? 0" />
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
    <div class="modal-backdrop" id="slConfirmModal" role="dialog" aria-modal="true" aria-labelledby="slConfirmTitle">
        <div class="modal">
            <h3 id="slConfirmTitle">Konfirmasi</h3>
            <p id="slConfirmMessage">Apakah Anda yakin ingin melanjutkan?</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-sl-confirm-cancel>Batal</button>
                <button type="button" class="btn btn-danger" data-sl-confirm-ok>Lanjutkan</button>
            </div>
        </div>
    </div>
    <div class="sl-toast-wrap" id="slToastWrap" aria-live="polite"></div>
    <script src="{{ asset('js/sl-password.js') }}" defer></script>
    <script>
    (function () {
        var sidebar = document.getElementById('slSidebar');
        var backdrop = document.getElementById('slBackdrop');
        function isMobileSidebar() { return window.matchMedia('(max-width: 768px)').matches; }
        function setSidebar(open) {
            if (!sidebar) return;
            sidebar.classList.toggle('open', !!open);
            if (backdrop) backdrop.classList.toggle('open', !!open && isMobileSidebar());
            document.querySelectorAll('[data-sl-toggle]').forEach(function (btn) {
                btn.setAttribute('aria-expanded', sidebar.classList.contains('open') ? 'true' : 'false');
            });
        }
        document.querySelectorAll('[data-sl-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setSidebar(!(sidebar && sidebar.classList.contains('open')));
            });
        });
        document.querySelectorAll('[data-sl-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setSidebar(false);
            });
        });
        // Backdrop: klik area kosong di luar sidebar langsung menutup.
        // Klik di dalam sidebar (termasuk menu) tidak menutup agar user
        // dapat memilih menu; sidebar tertutup saat navigasi terjadi.
        // Tidak ada stopPropagation di sidebar agar handler global
        // (tutup action-menu / profile-dropdown) tetap berjalan.
        if (backdrop) {
            backdrop.addEventListener('click', function () { setSidebar(false); });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isMobileSidebar()) setSidebar(false);
        });
        window.addEventListener('resize', function () {
            if (!isMobileSidebar()) setSidebar(false);
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
        // Action menu tabel Admin: satu terbuka dalam satu waktu, tutup
        // saat klik di luar atau tekan Escape. Murni tampilan; URL aksi
        // tetap dari backend.
        function closeAllMenus(except) {
            document.querySelectorAll('[data-sl-menu] .sl-menu.open').forEach(function (menu) {
                if (menu !== except) {
                    menu.classList.remove('open');
                    var btn = menu.parentElement.querySelector('.sl-menu-btn');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                }
            });
        }
        document.querySelectorAll('[data-sl-menu] > .sl-menu-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var menu = btn.parentElement.querySelector('.sl-menu');
                var willOpen = menu && !menu.classList.contains('open');
                closeAllMenus(menu);
                if (menu && willOpen) {
                    menu.classList.add('open');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });
        document.addEventListener('click', function () { closeAllMenus(null); closeProfileMenus(null); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeAllMenus(null); closeProfileMenus(null); }
        });
        // Dropdown profil header: satu terbuka, tutup klik-di-luar / tombol
        // yang sama / Escape. Murni tampilan; route tetap dari backend.
        function closeProfileMenus(except) {
            document.querySelectorAll('[data-sl-profile] .sl-profile-menu.open').forEach(function (menu) {
                if (menu !== except) {
                    menu.classList.remove('open');
                    var btn = menu.parentElement.querySelector('.sl-profile-btn');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                }
            });
        }
        document.querySelectorAll('[data-sl-profile] > .sl-profile-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var menu = btn.parentElement.querySelector('.sl-profile-menu');
                var willOpen = menu && !menu.classList.contains('open');
                closeAllMenus(null);
                closeProfileMenus(menu);
                if (menu && willOpen) {
                    menu.classList.add('open');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });
        });
        // Toast dari session flash: tampilkan sekali sebagai feedback UI
        // tanpa mengganti mekanisme flash backend.
        function showToast(message, type) {
            var wrap = document.getElementById('slToastWrap');
            if (!wrap || !message) return;
            var el = document.createElement('div');
            el.className = 'sl-toast ' + (type || 'success');
            el.setAttribute('role', 'status');
            var text = document.createElement('span');
            text.textContent = message;
            var close = document.createElement('button');
            close.type = 'button';
            close.className = 'sl-toast-close';
            close.setAttribute('aria-label', 'Tutup notifikasi');
            close.textContent = '×';
            close.addEventListener('click', function () { el.remove(); });
            el.appendChild(text);
            el.appendChild(close);
            wrap.appendChild(el);
            setTimeout(function () { if (el.parentNode) el.remove(); }, 5000);
        }
        document.querySelectorAll('.flash-success').forEach(function (el) {
            var msg = el.textContent.trim().replace(/\s+/g, ' ');
            if (msg) showToast(msg.length > 140 ? msg.slice(0, 140) + '…' : msg, 'success');
        });
        document.querySelectorAll('.flash-error').forEach(function (el) {
            var msg = el.textContent.trim().replace(/\s+/g, ' ');
            if (msg) showToast(msg.length > 140 ? msg.slice(0, 140) + '…' : msg, 'error');
        });
        // Modal konfirmasi generik untuk form onsubmit confirm(): gantikan
        // dialog browser dengan modal design system; backend confirm tetap
        // menjadi penentu akhir bila JS nonaktif.
        var confirmForm = null;
        var confirmModal = document.getElementById('slConfirmModal');
        var confirmMsg = document.getElementById('slConfirmMessage');
        document.querySelectorAll('form[onsubmit*="confirm("]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.slConfirmed) return;
                e.preventDefault();
                e.stopImmediatePropagation();
                confirmForm = form;
                var m = /confirm\('([^']+)'\)/.exec(form.getAttribute('onsubmit') || '');
                if (confirmMsg) confirmMsg.textContent = m ? m[1] : 'Apakah Anda yakin ingin melanjutkan?';
                if (confirmModal) confirmModal.classList.add('open');
            }, true);
        });
        document.querySelectorAll('[data-sl-confirm-cancel]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (confirmModal) confirmModal.classList.remove('open');
                confirmForm = null;
            });
        });
        document.querySelectorAll('[data-sl-confirm-ok]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (confirmModal) confirmModal.classList.remove('open');
                if (confirmForm) {
                    confirmForm.dataset.slConfirmed = '1';
                    confirmForm.removeAttribute('onsubmit');
                    confirmForm.submit();
                }
                confirmForm = null;
            });
        });
        // Search admin: debounce agar tidak submit tiap ketikan; filter
        // dropdown submit langsung (GET, page reset ke 1 via backend).
        // Pencarian tetap server-side; JS hanya UX.
        document.querySelectorAll('form[data-sl-search]').forEach(function (form) {
            var input = form.querySelector('[data-sl-search-input]');
            var timer = null;
            if (input) {
                input.addEventListener('input', function () {
                    if (timer) clearTimeout(timer);
                    timer = setTimeout(function () { form.submit(); }, 600);
                });
            }
            form.querySelectorAll('[data-sl-filter]').forEach(function (sel) {
                sel.addEventListener('change', function () { form.submit(); });
            });
        });
    })();
    </script>
</body>
</html>

