@extends('layouts.guru')

@section('title', 'Notifikasi')

@section('content')
<div class="sl-card">
    <h2>Notifikasi</h2>
    <p style="color:var(--muted);margin-top:4px;">Klik notifikasi untuk menandainya dibaca dan membuka laporan terkait.</p>
    <div style="margin-top:16px;">
        @include('notifications.list', ['readRoute' => fn ($n) => route('guru.notifications.read', $n)])
    </div>
</div>
@endsection
