<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} SI-LPiket</title>
</head>
<body>
    <h1>{{ $title }} SI-LPiket</h1>
    <p>Nama: {{ auth()->user()->name }}</p>
    <p>Role: {{ auth()->user()->role }}</p>
    <p>Status: {{ auth()->user()->status }}</p>

    <form id="logout-form" method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
</body>
</html>

