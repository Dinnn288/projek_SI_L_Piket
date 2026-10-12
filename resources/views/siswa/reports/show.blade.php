@extends('layouts.siswa')

@section('title', 'Detail Laporan')

@section('content')
<div class="sl-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; flex-wrap: wrap;">
        <h1 style="font-size: 22px; color: #1f3d2b;">{{ $report->title }}</h1>
        @if ($report->status === 'menunggu')
            <span class="badge badge-menunggu">Menunggu</span>
        @elseif ($report->status === 'selesai')
            <span class="badge badge-selesai">Selesai</span>
        @else
            <span class="badge badge-proses">{{ $report->status }}</span>
        @endif
    </div>
    <p class="muted text-sm mt-1">Kategori: {{ $report->category?->name }} | Prioritas: {{ $report->priority }} (dari kategori)</p>
    <p class="muted text-sm mt-1">Kelas: {{ $report->schoolClass?->name }} | Lokasi: {{ $report->location }}</p>
    <p class="muted text-sm mt-1">Dibuat: {{ $report->created_at?->format('d M Y H:i') }}</p>
    @if (session('success'))
        <p style="margin-top: 10px; color: #1e8449; font-weight: 600;">{{ session('success') }}</p>
    @endif
    <div style="margin-top: 16px;">
        <h2 style="font-size: 16px;">Deskripsi</h2>
        <p style="margin-top: 6px; white-space: pre-line;">{{ $report->description }}</p>
    </div>
    <div style="margin-top: 16px;">
        <h2 style="font-size: 16px;">Foto bukti ({{ $report->attachments->count() }})</h2>
        <div class="photo-grid">
            @foreach ($report->attachments as $attachment)
                <div>
                    <img src="{{ route('siswa.reports.attachment', [$report, $attachment]) }}" alt="{{ $attachment->original_name }}" loading="lazy">
                    <div class="caption">{{ $attachment->original_name }} ({{ number_format($attachment->file_size / 1024, 1) }} KB)</div>
                </div>
            @endforeach
        </div>
    </div>
    <div style="margin-top: 18px;">
        <a href="{{ route('siswa.reports.index') }}" class="btn btn-secondary">Kembali ke Laporan Saya</a>
        <a href="{{ route('siswa.dashboard') }}" class="btn" style="margin-left: 10px;">Dashboard</a>
    </div>
</div>
@endsection


