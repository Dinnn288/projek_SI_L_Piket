<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login SI-LPiket</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/si-lpiket.css') }}">
    <script src="{{ asset('js/sl-password.js') }}" defer></script>
    <style>
        .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: linear-gradient(135deg, #1a365d 0%, #2563eb 100%); }
        .login-card { background: #fff; border-radius: 14px; box-shadow: var(--shadow); max-width: 420px; width: 100%; padding: 36px 32px; }
        .login-brand h1 { font-size: 22px; color: var(--navy); }
        .login-brand p { color: var(--muted); font-size: 13px; margin-top: 4px; }
        @media (max-width: 480px) {
            .login-card { padding: 28px 22px; }
            .login-logo { width: 80px; height: 80px; border-radius: 18px; }
        }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-brand">
                <img class="login-logo" src="{{ asset('images/Logo-si-lpiket.jpg') }}" alt="Logo SI-LPiket" width="96" height="96">
                <h1>SI-LPiket</h1>
                <p>Sistem Informasi Laporan Piket</p>
            </div>
            <p style="color:var(--muted);font-size:13px;margin-bottom:16px;">Gunakan username dan password akun Anda.</p>
            @if ($errors->any())
                <div class="flash flash-error">
                    <ul style="margin:0;padding-left:20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div style="margin-bottom:14px;">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus>
                </div>
                <div style="margin-bottom:18px;">
                    <label for="password">Password</label>
                    <div class="sl-pw-field" data-sl-pw-field>
                        <input id="password" type="password" name="password" required>
                        <button type="button" class="sl-pw-toggle" data-sl-pw-toggle aria-label="Tampilkan password" aria-pressed="false" title="Tampilkan password"></button>
                    </div>
                </div>
                <button type="submit" class="btn" style="width:100%;">Masuk</button>
            </form>
        </div>
    </div>
</body>
</html>

