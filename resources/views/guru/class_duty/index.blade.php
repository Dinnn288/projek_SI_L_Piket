@extends('layouts.guru')

@section('title', 'Pemantauan Piket Kelas')

@section('content')
<div class="sl-card">
    <h1 style="font-size: 22px; color: #1a3d5c;">Pemantauan Piket Kelas</h1>
    <p class="muted text-sm mt-1">Tanggal: {{ $date }} | Hari: {{ $day ?? 'di luar jadwal (minggu)' }} | Waktu server: {{ ($serverNow ?? now())->format('d M Y H:i:s') }} WIB | Status piket Anda: {{ $onDutyDay ? 'Bertugas' : 'Tidak bertugas' }}</p>
    @if (! $onDutyDay)
        <p style="margin-top: 10px; color: #b7950b;">Anda tidak terjadwal piket hari ini. Pemantauan tetap ditampilkan, tetapi tombol Setujui / Kembalikan dinonaktifkan.</p>
    @endif
    <div class="sl-grid stat-compact">
        <div class="stat info"><h3>Terjadwal hari ini</h3><div class="value">{{ $scheduledCount }}</div></div>
        <div class="stat warning"><h3>Belum melapor</h3><div class="value">{{ $missingCount }}</div></div>
        <div class="stat"><h3>Menunggu verifikasi</h3><div class="value">{{ $waitingCount }}</div></div>
        <div class="stat"><h3>Perlu diperbaiki</h3><div class="value">{{ $fixCount }}</div></div>
        <div class="stat success"><h3>Sudah piket</h3><div class="value">{{ $doneCount }}</div></div>
        <div class="stat"><h3>Tidak ada piket</h3><div class="value">{{ $noStudentsScheduledCount ?? 0 }}</div></div>
    </div>
</div>

<div class="sl-card">
    <div class="sl-card-head">
        <h2 style="font-size: 18px; color: #1a3d5c;">Daftar Kelas</h2>
    </div>
    <x-admin-search placeholder="Cari nama kelas, jurusan...">
        <x-admin-filter name="status" label="Jadwal" placeholder="Jadwal: Semua" :options="['terjadwal' => 'Terjadwal hari ini', 'tidak_dijadwalkan' => 'Tidak dijadwalkan', 'tidak_ada_piket' => 'Tidak ada piket']" />
    </x-admin-search>
    <div class="table-wrap">
        <table style="margin-top: 12px;">
            <thead><tr><th>Kelas</th><th>Tingkat</th><th>Jurusan</th><th>Jadwal hari ini</th><th>Status laporan</th><th>Dikirim</th><th>Pelapor</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse ($classes as $row)
                    @php $class = $row['class']; @endphp
                    <tr>
                        <td>{{ $class->name }}</td>
                        <td>{{ \App\Services\ClassDutyService::gradeFromName($class->name) }}</td>
                        <td>{{ $class->major?->name ?? '-' }}</td>
                        <td>
                            @if ($row['scheduled'])
                                <span class="badge badge-aktif">Terjadwal</span>
                            @else
                                <span class="badge badge-nonaktif">Tidak dijadwalkan</span>
                            @endif
                        </td>
                        <td>
                            @php $st = $row['status']; @endphp
                            @if ($st === 'sudah_piket')
                                <span class="badge badge-selesai">Sudah piket</span>
                            @elseif ($st === 'menunggu_verifikasi')
                                <span class="badge badge-menunggu">Menunggu verifikasi</span>
                            @elseif ($st === 'perlu_diperbaiki')
                                <span class="badge badge-forward">Perlu diperbaiki</span>
                            @elseif ($st === 'belum_melapor')
                                <span class="badge badge-proses">Belum melapor</span>
                            @elseif ($st === 'tidak_ada_piket')
                                <span class="badge badge-nonaktif">Tidak ada piket</span>
                            @else
                                <span class="badge badge-nonaktif">Tidak dijadwalkan</span>
                            @endif
                        </td>
                        <td>{{ $row['sent_at']?->format('d M Y H:i') ?? '-' }}</td>
                        <td>{{ $row['reporter'] ?? '-' }}</td>
                        <td><a href="{{ route('guru.class-duty.show', $class) }}">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty">Belum ada kelas aktif.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-pagination :paginator="$classes" />
    <p class="muted text-sm mt-1">Sumber: data master kelas aktif. Kelas yang belum melapor tetap muncul. Klik Detail untuk melihat siswa bertugas hari ini.</p>
</div>
@endsection
