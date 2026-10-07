@extends('layouts.kepala-sekolah')

@section('title', 'Dashboard Kepala Sekolah')

@section('content')
<div class="sl-card">
    <h1 style="font-size: 24px; color: #4a235a;">Halo, {{ auth()->user()->name }}</h1>
    <p class="muted text-sm mt-1">Laporan yang diteruskan Guru Piket untuk ditindaklanjuti.</p>
    <div class="sl-grid stat-compact">
        <div class="stat warning"><h3>Diteruskan</h3><div class="value">{{ $forwardedCount }}</div></div>
        <div class="stat success"><h3>Selesai</h3><div class="value">{{ $completedCount }}</div></div>
    </div>
</div>

<div class="sl-card">
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

<div class="sl-card">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
        <h2 style="font-size: 18px; color: #4a235a;">Laporan Sedang Ditangani Guru</h2>
        <small style="color: var(--muted);" id="slMonitoringMeta">● Monitoring aktif</small>
    </div>
    <p class="muted text-sm mt-1">Terakhir diperbarui: <span id="slMonitoringTime">-</span> (WIB)</p>
    <div id="slMonitoringError" class="flash flash-error" style="display:none; margin-top:10px;">Pembaruan otomatis gagal. Tabel menampilkan data terakhir. Mencoba lagi…</div>
    <div class="table-wrap" style="margin-top:12px;">
        <table>
            <thead><tr><th>No</th><th>Laporan</th><th>Pelapor</th><th>Kelas/Lokasi</th><th>Guru Penanganan</th><th>Diambil Pada</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody id="slMonitoringBody">
                <tr><td colspan="8" style="text-align:center; color: var(--muted);">Memuat data monitoring…</td></tr>
            </tbody>
        </table>
    </div>
    <script>
    (function () {
        var body = document.getElementById('slMonitoringBody');
        var timeEl = document.getElementById('slMonitoringTime');
        var metaEl = document.getElementById('slMonitoringMeta');
        var errEl = document.getElementById('slMonitoringError');
        var endpoint = @json(route('kepala-sekolah.monitoring.json'));
        var intervalMs = 15000;

        function esc(value) {
            return String(value ?? '-').replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function render(reports) {
            if (!reports.length) {
                body.innerHTML = '<tr><td colspan="8" style="text-align:center; color: var(--muted);">Belum ada laporan yang sedang ditangani guru.</td></tr>';
                return;
            }
            body.innerHTML = reports.map(function (r, i) {
                return '<tr>'
                    + '<td>' + (i + 1) + '</td>'
                    + '<td>' + esc(r.title) + '</td>'
                    + '<td>' + esc(r.student) + '</td>'
                    + '<td>' + esc(r.class) + (r.location ? ' - ' + esc(r.location) : '') + '</td>'
                    + '<td>' + esc(r.teacher) + '</td>'
                    + '<td>' + esc(r.assigned_at) + '</td>'
                    + '<td><span class="badge badge-proses">Dalam Penanganan</span></td>'
                    + '<td><a href="' + encodeURI(r.detail_url) + '">Lihat Detail</a></td>'
                    + '</tr>';
            }).join('');
        }

        function refresh() {
            fetch(endpoint, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
                .then(function (data) {
                    render(data.reports || []);
                    if (timeEl) timeEl.textContent = data.server_time || '-';
                    if (metaEl) metaEl.textContent = '● Monitoring aktif (' + (data.count || 0) + ' laporan)';
                    if (errEl) errEl.style.display = 'none';
                })
                .catch(function () {
                    if (errEl) errEl.style.display = 'block';
                    if (metaEl) metaEl.textContent = '● Pembaruan gagal, mencoba lagi…';
                });
        }

        refresh();
        setInterval(refresh, intervalMs);
    })();
    </script>
</div>

<div class="sl-card">
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


