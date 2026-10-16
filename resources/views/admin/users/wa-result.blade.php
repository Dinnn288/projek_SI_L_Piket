@extends('layouts.admin')

@section('title', 'Konfirmasi WhatsApp')

@section('content')
<div class="sl-card">
    <h1>Konfirmasi Pengiriman WhatsApp</h1>
    <p class="muted text-sm mt-1">Akun berhasil dibuat. Periksa kembali data di bawah sebelum pesan dikirim. Pesan hanya terkirim setelah Anda menekan tombol konfirmasi.</p>

    <dl class="mt-3">
        <div class="mt-2"><dt>Nama penerima</dt><dd>{{ $user->name }}</dd></div>
        <div class="mt-2"><dt>Role akun</dt><dd>{{ $user->role }}</dd></div>
        <div class="mt-2"><dt>Username</dt><dd>{{ $user->username }}</dd></div>
        <div class="mt-2"><dt>Nomor WhatsApp tujuan</dt><dd>{{ $target }}</dd></div>
        @if ($plainPassword ?? null)
            <div class="mt-2">
                <dt>Password awal (hanya halaman ini)</dt>
                <dd>
                    <span class="sl-pw-field" data-sl-pw-field>
                        <input type="password" value="{{ $plainPassword }}" readonly>
                        <button type="button" class="sl-pw-toggle" data-sl-pw-toggle aria-label="Tampilkan password" aria-pressed="false" title="Tampilkan password"></button>
                    </span>
                </dd>
            </div>
        @endif
    </dl>

    @if ($preview ?? null)
        <div class="mt-3">
            <h2>Preview pesan yang akan dikirim</h2>
            <pre class="mt-2">{{ $preview }}</pre>
        </div>
    @endif

    @if ($status)
        <div class="mt-3">
            @if (($status['outcome'] ?? null) === 'sent')
                <div class="flash flash-success">Pesan WhatsApp masuk antrean pengiriman Fonnte.</div>
            @elseif (($status['outcome'] ?? null) === 'uncertain')
                <div class="flash flash-error">Hasil tidak pasti: pesan mungkin sudah terkirim. Periksa ke penerima sebelum mengirim ulang.</div>
            @else
                <div class="flash flash-error">Pengiriman gagal. Anda dapat mencoba lagi selama data konfirmasi masih tersedia.</div>
            @endif
        </div>
    @endif

    <div class="mt-3">
        @if ($hasSecret)
            @if (($status['outcome'] ?? null) === 'failed')
                <button type="button" class="btn" data-sl-wa-open>Coba Kirim Lagi</button>
            @else
                <button type="button" class="btn" data-sl-wa-open>Buka Konfirmasi &amp; Kirim</button>
            @endif
        @endif
        <form method="POST" action="{{ route('admin.users.wa.cancel', $dispatchId) }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-secondary">Kembali Tanpa Mengirim</button>
        </form>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Ke Daftar Users</a>
    </div>
</div>

<div class="modal-backdrop" id="slWaModal" role="dialog" aria-modal="true" aria-labelledby="slWaTitle">
    <div class="modal">
        <h3 id="slWaTitle">Kirim kredensial via WhatsApp?</h3>
        <p>Pesan berisi username dan password awal akan dikirim ke <strong>{{ $target }}</strong> untuk <strong>{{ $user->name }}</strong> ({{ $user->role }}).</p>
        <p class="muted text-sm mt-1">Password asli hanya tersedia selama halaman konfirmasi ini. Setelah dikirim, dibatalkan, atau kedaluwarsa, pengiriman ulang memerlukan password baru via form edit.</p>
        <form id="slWaSendForm" method="POST" action="{{ $hasSecret ? route('admin.users.wa.send', $dispatchId) : '#' }}">
            @csrf
            <input type="hidden" name="target" value="{{ $target }}">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-sl-wa-close>Kembali Periksa Data</button>
                <button type="submit" class="btn" data-sl-wa-submit @disabled(! $hasSecret)>Konfirmasi dan Kirim WhatsApp</button>
            </div>
        </form>
        @if (! $hasSecret)
            <p class="muted text-sm mt-1">Data kredensial sudah tidak tersedia (terkirim/kedaluwarsa). Muat ulang tidak akan mengirim ulang password.</p>
        @endif
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('slWaModal');
    var form = document.getElementById('slWaSendForm');
    document.querySelectorAll('[data-sl-wa-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (modal) modal.classList.add('open');
        });
    });
    document.querySelectorAll('[data-sl-wa-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (modal) modal.classList.remove('open');
        });
    });
    if (form) {
        // Tandai form ini agar handler anti-ganda global (app-shell)
        // tidak ikut mengunci tombol yang salah pada form modal.
        form.dataset.slWaForm = '1';
        form.addEventListener('submit', function (e) {
            if (form.dataset.slLocked) { e.preventDefault(); return; }
            form.dataset.slLocked = '1';
            var btn = form.querySelector('[data-sl-wa-submit]');
            if (btn) { btn.disabled = true; btn.textContent = 'Mengirim...'; }
        });
    }
})();
</script>
@endsection
