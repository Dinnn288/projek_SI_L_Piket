@extends('layouts.guru')

@section('title', 'Dashboard Guru Piket')

@section('content')
<div class="sl-card">
    <h1 style="font-size: 24px; color: #1a3d5c;">Halo, {{ auth()->user()->name }}</h1>
    <p class="muted text-sm mt-1">Hari ini: {{ $day ?? 'di luar jadwal (minggu)' }} | Waktu server: {{ ($serverNow ?? now())->format('d M Y H:i:s') }} WIB | Status piket: {{ $onDutyDay ? 'Bertugas' : 'Tidak bertugas' }}</p>
    @if (! $onDutyDay)
        <p style="margin-top: 10px; color: #b7950b;">Anda tidak terjadwal piket hari ini, sehingga laporan menunggu tidak ditampilkan dan tombol Ambil Laporan dinonaktifkan.</p>
    @endif
    <div class="sl-grid stat-compact">
        <div class="stat"><h3>Menunggu</h3><div class="value">{{ $waitingCount }}</div></div>
        <div class="stat"><h3>Lanjutan</h3><div class="value">{{ $continuedCount }}</div></div>
        <div class="stat"><h3>Ditangani</h3><div class="value">{{ $mineActive }}</div></div>
        <div class="stat success"><h3>Total</h3><div class="value">{{ $mineTotal }}</div></div>
    </div>
</div>

<div class="sl-card">
    <h2 style="font-size: 18px; color: #1a3d5c;">🔔 Notifikasi ({{ $unreadCount ?? 0 }} belum dibaca)</h2>
    @if (($notifications ?? collect())->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Belum ada notifikasi.</p>
    @else
        <ul style="margin-top: 10px; padding-left: 20px;">
            @foreach ($notifications as $notification)
                <li style="margin-bottom: 6px;">
                    <strong>{{ $notification->title }}</strong> — {{ $notification->message }}
                    @if ($notification->report_id)
                        <a href="{{ route('guru.reports.show', $notification->report_id) }}">Lihat laporan</a>
                    @endif
                    <small style="color: #7f8c8d;">({{ $notification->created_at?->format('d M Y H:i') }}{{ $notification->is_read ? '' : ', belum dibaca' }})</small>
                </li>
            @endforeach
        </ul>
    @endif
</div>

<div class="sl-card">
    <h2 style="font-size: 18px; color: #1a3d5c;">Laporan menunggu</h2>
    @if ($waiting->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Tidak ada laporan menunggu untuk jadwal piket Anda.</p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 12px;">
            <thead><tr><th>Judul</th><th>Kategori</th><th>Kelas</th><th>Pelapor</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($waiting as $report)
                    <tr>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->category?->name }} ({{ $report->priority }})</td>
                        <td>{{ $report->schoolClass?->name }}</td>
                        <td>{{ $report->student?->userBaru?->name }}</td>
                        <td>{{ $report->created_at?->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('guru.reports.show', $report) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

<div class="sl-card">
    <h2 style="font-size: 18px; color: #1a3d5c;">Lanjutan dari Hari Sebelumnya</h2>
    <p class="muted text-sm mt-1">Laporan dalam penanganan yang assignment-nya dibuat sebelum hari ini dan dapat diambil alih Guru Piket yang bertugas.</p>
    @if ($continued->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Tidak ada laporan lanjutan untuk jadwal piket Anda.</p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 12px;">
            <thead><tr><th>Judul</th><th>Kategori</th><th>Kelas</th><th>Guru sebelumnya</th><th>Diambil sebelumnya</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($continued as $report)
                    <tr>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->category?->name }} ({{ $report->priority }})</td>
                        <td>{{ $report->schoolClass?->name }}</td>
                        <td>{{ $report->assignedTeacher?->userBaru?->name }}</td>
                        <td>{{ $report->assigned_at?->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('guru.reports.show', $report) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

<div class="sl-card">
    <h2 style="font-size: 18px; color: #1a3d5c;">Pemantauan Piket Kelas ({{ $classDate ?? '-' }})</h2>
    <p class="muted text-sm mt-1">Terjadwal: {{ $scheduledClassCount ?? 0 }} | Belum melapor: {{ $classDutyMissing ?? 0 }} | Menunggu verifikasi: {{ $classDutyWaiting ?? 0 }} | Perlu diperbaiki: {{ $classDutyFix ?? 0 }} | Sudah piket: {{ $classDutyDone ?? 0 }} | <a href="{{ route('guru.class-duty.index') }}">Buka pemantauan seluruh kelas</a></p>
    @if (($classDutyLatest ?? collect())->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Belum ada laporan piket kelas hari ini.</p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 12px;">
            <thead><tr><th>Kelas</th><th>Perwakilan</th><th>Status</th><th>Dikirim</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($classDutyLatest as $dutyReport)
                    <tr>
                        <td>{{ $dutyReport->schoolClass?->name }}</td>
                        <td>{{ $dutyReport->student?->userBaru?->name }}</td>
                        <td>
                            @if ($dutyReport->status === 'disetujui')
                                <span class="badge badge-selesai">Sudah piket</span>
                            @elseif ($dutyReport->status === 'perlu_diperbaiki')
                                <span class="badge badge-forward">Perlu diperbaiki</span>
                            @else
                                <span class="badge badge-menunggu">Menunggu verifikasi</span>
                            @endif
                        </td>
                        <td>{{ $dutyReport->created_at?->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('guru.class-duty.report', $dutyReport) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

<div class="sl-card">
    <h2 style="font-size: 18px; color: #1a3d5c;">Laporan yang saya tangani</h2>
    @if ($mine->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Belum ada laporan yang Anda ambil.</p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 12px;">
            <thead><tr><th>Judul</th><th>Kategori</th><th>Status</th><th>Diambil</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($mine as $report)
                    <tr>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->category?->name }}</td>
                        <td><span class="badge badge-proses">{{ $report->status }}</span></td>
                        <td>{{ $report->assigned_at?->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('guru.reports.show', $report) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
@endsection


