<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login SI-LPiket</title>
</head>
<body>
    <h1>Login SI-LPiket</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div>
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus>
        </div>
        <div>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>
        </div>
        <button type="submit">Masuk</button>
    </form>

    @auth
        <form id="logout-form" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
        </form>
    @endauth
</body>
</html>
