@extends('layouts.siswa')

@section('title', 'Dashboard Siswa')

@section('content')
<div class="card">
    <h1 style="font-size: 24px; color: #1f3d2b;">Halo, {{ auth()->user()->name }}</h1>
    <p style="color: #5d6d7e; margin-top: 6px;">Kelas: {{ $student->schoolClass?->name ?? '-' }} | NIS: {{ $student->nis }}</p>
    <p style="color: #5d6d7e; margin-top: 6px;">Buat laporan masalah di lingkungan kelas/sekolah dengan foto sebagai bukti.</p>
    <div style="margin-top: 16px;">
        <a href="{{ route('siswa.reports.create') }}" class="btn">Buat Laporan</a>
        <a href="{{ route('siswa.reports.index') }}" class="btn btn-secondary" style="margin-left: 10px;">Laporan Saya</a>
    </div>
    <div class="grid">
        <div class="stat"><h3>Total laporan saya</h3><div class="value">{{ $total }}</div></div>
        <div class="stat"><h3>Menunggu</h3><div class="value">{{ $menunggu }}</div></div>
        <div class="stat"><h3>Diproses</h3><div class="value">{{ $diproses }}</div></div>
        <div class="stat"><h3>Selesai</h3><div class="value">{{ $selesai }}</div></div>
    </div>
    <div class="status-info">
        <div class="status-card"><strong>Menunggu</strong>Laporan sedang menunggu penanganan guru piket.</div>
        <div class="status-card"><strong>Diproses</strong>Laporan sedang dalam penanganan atau diteruskan.</div>
        <div class="status-card"><strong>Selesai</strong>Laporan sudah selesai ditangani.</div>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <h2 style="font-size: 18px; color: #1f3d2b;">🔔 Notifikasi ({{ $unreadCount ?? 0 }} belum dibaca)</h2>
    @if (($notifications ?? collect())->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Belum ada notifikasi.</p>
    @else
        <ul style="margin-top: 10px; padding-left: 20px;">
            @foreach ($notifications as $notification)
                <li style="margin-bottom: 6px;">
                    <strong>{{ $notification->title }}</strong> — {{ $notification->message }}
                    @if ($notification->report_id)
                        <a href="{{ route('siswa.reports.show', $notification->report_id) }}">Lihat laporan</a>
                    @endif
                    <small style="color: #7f8c8d;">({{ $notification->created_at?->format('d M Y H:i') }}{{ $notification->is_read ? '' : ', belum dibaca' }})</small>
                </li>
            @endforeach
        </ul>
    @endif
</div>

<div class="card" style="margin-top: 20px;">
    <h2 style="font-size: 18px; color: #1f3d2b;">Laporan terbaru</h2>
    @if ($latest->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Belum ada laporan. <a href="{{ route('siswa.reports.create') }}">Buat Laporan</a></p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 12px;">
            <thead><tr><th>Judul</th><th>Kategori</th><th>Status</th><th>Foto</th><th>Dibuat</th></tr></thead>
            <tbody>
                @foreach ($latest as $report)
                    <tr>
                        <td><a href="{{ route('siswa.reports.show', $report) }}">{{ $report->title }}</a></td>
                        <td>{{ $report->category?->name }}</td>
                        <td>
                            @if ($report->status === 'menunggu')
                                <span class="badge badge-menunggu">Menunggu</span>
                            @elseif ($report->status === 'selesai')
                                <span class="badge badge-selesai">Selesai</span>
                            @else
                                <span class="badge badge-proses">{{ $report->status }}</span>
                            @endif
                        </td>
                        <td>{{ $report->attachments->count() }}</td>
                        <td>{{ $report->created_at?->format('d M Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
@endsection
