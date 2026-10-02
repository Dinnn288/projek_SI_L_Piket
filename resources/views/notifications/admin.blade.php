@extends('layouts.admin')

@section('title', 'Notifikasi')

@section('content')
<div class="sl-card">
    <h2>Notifikasi</h2>
    <p style="color:var(--muted);margin-top:4px;">Notifikasi workflow laporan tidak ditujukan ke Admin; halaman ini menampilkan notifikasi milik akun Anda bila ada.</p>
    <div style="margin-top:16px;">
        @include('notifications.list', ['readRoute' => fn ($n) => route('admin.notifications.read', $n)])
    </div>
</div>
@endsection
