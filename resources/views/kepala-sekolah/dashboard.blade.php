@extends('layouts.kepala-sekolah')

@section('title', 'Dashboard Kepala Sekolah')

@section('content')
<div class="card">
    <h1 style="font-size: 24px; color: #4a235a;">Halo, {{ auth()->user()->name }}</h1>
    <p style="color: #5d6d7e; margin-top: 6px;">Laporan yang diteruskan Guru Piket untuk ditindaklanjuti.</p>
    <div class="grid">
        <div class="stat"><h3>Laporan Diteruskan</h3><div class="value">{{ $forwardedCount }}</div></div>
        <div class="stat"><h3>Laporan Selesai</h3><div class="value">{{ $completedCount }}</div></div>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 18px; color: #4a235a;">Laporan Diteruskan Terbaru</h2>
        <a href="{{ route('kepala-sekolah.reports.index') }}" class="btn">Lihat Semua</a>
    </div>
    @if ($latest->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Belum ada laporan yang diteruskan.</p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 12px;">
            <thead><tr><th>Judul</th><th>Kategori</th><th>Pelapor / Kelas</th><th>Guru penerus</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($latest as $report)
                    <tr>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->category?->name }} ({{ $report->priority }})</td>
                        <td>{{ $report->student?->userBaru?->name }} / {{ $report->schoolClass?->name }}</td>
                        <td>{{ $report->assignedTeacher?->userBaru?->name }}</td>
                        <td>{{ $report->created_at?->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('kepala-sekolah.reports.show', $report) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

<div class="card" style="margin-top: 20px;">
    <h2 style="font-size: 18px; color: #4a235a;">🔔 Notifikasi ({{ $unreadCount ?? 0 }} belum dibaca)</h2>
    @if (($notifications ?? collect())->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Belum ada notifikasi.</p>
    @else
        <ul style="margin-top: 10px; padding-left: 20px;">
            @foreach ($notifications as $notification)
                <li style="margin-bottom: 6px;">
                    <strong>{{ $notification->title }}</strong> — {{ $notification->message }}
                    @if ($notification->report_id)
                        <a href="{{ route('kepala-sekolah.reports.show', $notification->report_id) }}">Lihat laporan</a>
                    @endif
                    <small style="color: #7f8c8d;">({{ $notification->created_at?->format('d M Y H:i') }}{{ $notification->is_read ? '' : ', belum dibaca' }})</small>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
