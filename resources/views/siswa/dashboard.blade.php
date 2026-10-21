@extends('layouts.siswa')

@section('title', 'Dashboard Siswa')

@section('content')
<div class="sl-card">
    <h2>Halo, {{ auth()->user()->name }}</h2>
    <p style="color:var(--muted);margin-top:4px;">Kelas: {{ $student->schoolClass?->name ?? '-' }} | NIS: {{ $student->nis }}</p>
    <p style="color:var(--muted);margin-top:4px;">Buat laporan masalah di lingkungan kelas/sekolah dengan foto sebagai bukti.</p>
    <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="{{ route('siswa.reports.create') }}" class="btn">Buat Laporan</a>
        <a href="{{ route('siswa.reports.index') }}" class="btn btn-secondary">Laporan Saya</a>
    </div>
    <div class="sl-grid stat-compact">
        <div class="stat"><h3>Total laporan saya</h3><div class="value">{{ $total }}</div></div>
        <div class="stat"><h3>Menunggu</h3><div class="value">{{ $menunggu }}</div></div>
        <div class="stat"><h3>Diproses</h3><div class="value">{{ $diproses }}</div></div>
        <div class="stat success"><h3>Selesai</h3><div class="value">{{ $selesai }}</div></div>
    </div>
</div>

<div class="sl-card">
    <h3>Piket Kelas Hari Ini ({{ $classDate ?? '-' }})</h3>
    <p style="color:var(--muted);margin-top:4px;">Jadwal:
        @if ($classScheduled ?? false)
            <span class="badge badge-aktif">Terjadwal</span>
        @else
            <span class="badge badge-nonaktif">Tidak dijadwalkan</span>
        @endif
        | Status:
        @if (($classStatus ?? '') === 'sudah_piket')
            <span class="badge badge-selesai">Sudah piket</span>
        @elseif (($classStatus ?? '') === 'menunggu_verifikasi')
            <span class="badge badge-menunggu">Menunggu verifikasi</span>
        @elseif (($classStatus ?? '') === 'perlu_diperbaiki')
            <span class="badge badge-forward">Perlu diperbaiki</span>
        @elseif (($classStatus ?? '') === 'belum_melapor')
            <span class="badge badge-proses">Belum melapor</span>
        @else
            <span class="badge badge-nonaktif">Tidak dijadwalkan</span>
        @endif
        <a href="{{ route('siswa.class-duty.index') }}" style="margin-left: 8px;">Buka Piket Kelas</a>
    </p>
</div>

<div class="sl-card">
    <h3>🔔 Notifikasi ({{ $unreadCount ?? 0 }} belum dibaca)</h3>
    @if (($notifications ?? collect())->isEmpty())
        <p class="empty">Belum ada notifikasi.</p>
    @else
        <ul style="margin-top:10px;padding-left:20px;">
            @foreach ($notifications as $notification)
                <li style="margin-bottom:6px;">
                    <strong>{{ $notification->title }}</strong> — {{ $notification->message }}
                    @if ($notification->report_id)
                        <a href="{{ route('siswa.reports.show', $notification->report_id) }}">Lihat laporan</a>
                    @endif
                    <small style="color:var(--muted);">({{ $notification->created_at?->format('d M Y H:i') }}{{ $notification->is_read ? '' : ', belum dibaca' }})</small>
                </li>
            @endforeach
        </ul>
    @endif
</div>

<div class="sl-card">
    <h3>Laporan terbaru</h3>
    @if ($latest->isEmpty())
        <p class="empty">Belum ada laporan. <a href="{{ route('siswa.reports.create') }}">Buat Laporan</a></p>
    @else
        <div class="table-wrap" style="margin-top:12px;border:none;">
        <table>
            <thead><tr><th>Judul</th><th>Kategori</th><th>Status</th><th>Foto</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($latest as $report)
                    <tr>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->category?->name }}</td>
                        <td><x-status-badge :value="$report->status" /></td>
                        <td>{{ $report->attachments->count() }}</td>
                        <td>{{ $report->created_at?->format('d M Y H:i') }}</td>
                        <td class="sl-actions"><a href="{{ route('siswa.reports.show', $report) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
@endsection

