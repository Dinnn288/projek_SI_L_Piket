@extends('layouts.guru')

@section('title', 'Detail Piket Kelas')

@section('content')
<div class="sl-card">
    <h1 style="font-size: 22px; color: #1a3d5c;">{{ $class->name }}</h1>
    <p class="muted text-sm mt-1">Jurusan: {{ $class->major?->name ?? '-' }} | Tingkat: {{ \App\Services\ClassDutyService::gradeFromName($class->name) }} | Tanggal: {{ $date }} ({{ $day ?? 'minggu' }})</p>
    <p class="muted text-sm mt-1">Jadwal hari ini:
        @if ($scheduled)
            <span class="badge badge-aktif">Terjadwal</span>
        @else
            <span class="badge badge-nonaktif">Tidak dijadwalkan</span>
        @endif
        | Status laporan:
        @if ($status === 'sudah_piket')
            <span class="badge badge-selesai">Sudah piket</span>
        @elseif ($status === 'menunggu_verifikasi')
            <span class="badge badge-menunggu">Menunggu verifikasi</span>
        @elseif ($status === 'perlu_diperbaiki')
            <span class="badge badge-forward">Perlu diperbaiki</span>
        @elseif ($status === 'belum_melapor')
            <span class="badge badge-proses">Belum melapor</span>
        @else
            <span class="badge badge-nonaktif">Tidak dijadwalkan</span>
        @endif
    </p>
    @if (! $scheduled)
        <p style="margin-top: 12px; color: #7f8c8d;">Kelas tidak dijadwalkan piket pada hari ini. Kelas tidak dihitung sebagai belum melapor atau terlambat.</p>
    @endif
    @if ($report)
        <p class="muted text-sm mt-1">Dikirim: {{ $report->created_at?->format('d M Y H:i') }} WIB oleh {{ $report->student?->userBaru?->name ?? '-' }}
            @if ($report->status === 'disetujui')
                | Disetujui oleh {{ $report->verifiedBy?->userBaru?->name ?? '-' }} pada {{ $report->verified_at?->format('d M Y H:i') }}
            @elseif ($report->status === 'perlu_diperbaiki')
                | Dikembalikan: {{ $report->return_note }}
            @endif
            | <a href="{{ route('guru.class-duty.report', $report) }}">Buka laporan</a>
        </p>
    @elseif ($scheduled)
        <p style="margin-top: 12px; color: #b7950b;">Kelas dijadwalkan tetapi belum mengirim laporan.</p>
    @endif
</div>

<div class="sl-card">
    <h2 style="font-size: 18px; color: #1a3d5c;">Siswa bertugas hari ini ({{ $students->count() }} orang)</h2>
    <p class="muted text-sm mt-1">Hanya siswa yang dijadwalkan pada hari dan tanggal berjalan. Seluruh siswa kelas bertugas bersama.</p>
    @if ($students->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Tidak ada siswa bertugas (kelas tidak dijadwalkan atau tidak ada siswa aktif).</p>
    @else
        <div class="table-wrap">
            <table style="margin-top: 12px;">
                <thead><tr><th>No</th><th>Nama</th><th>NIS</th><th>Status akun</th></tr></thead>
                <tbody>
                    @foreach ($students as $i => $student)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $student->userBaru?->name ?? '-' }}</td>
                            <td>{{ $student->nis }}</td>
                            <td>{{ $student->userBaru?->status ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <div style="margin-top: 14px;">
        <a href="{{ route('guru.class-duty.index') }}" class="btn btn-secondary">Kembali ke daftar kelas</a>
    </div>
</div>
@endsection
