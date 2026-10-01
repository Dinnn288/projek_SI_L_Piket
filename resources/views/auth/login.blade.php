<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login SI-LPiket</title>
    <link rel="stylesheet" href="{{ asset('css/si-lpiket.css') }}">
    <style>
        .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: linear-gradient(135deg, #1a365d 0%, #2563eb 100%); }
        .login-card { background: #fff; border-radius: 14px; box-shadow: var(--shadow); max-width: 420px; width: 100%; padding: 36px 32px; }
        .login-brand h1 { font-size: 22px; color: var(--navy); }
        .login-brand p { color: var(--muted); font-size: 13px; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-brand">
                <h1>SI-LPiket</h1>
                <p>Sistem Informasi Laporan Piket</p>
            </div>
            <h2 style="font-size:18px;margin:18px 0 4px;">Masuk</h2>
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
                    <input id="password" type="password" name="password" required>
                </div>
                <button type="submit" class="btn" style="width:100%;">Masuk</button>
            </form>
        </div>
    </div>
</body>
</html>

