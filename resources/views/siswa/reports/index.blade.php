@extends('layouts.siswa')

@section('title', 'Laporan Saya')

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <h1 style="font-size: 22px; color: #1f3d2b;">Laporan Saya</h1>
        <a href="{{ route('siswa.reports.create') }}" class="btn">Buat Laporan</a>
    </div>
    <p style="color: #5d6d7e; margin-top: 6px;">Kelas: {{ $student->schoolClass?->name ?? '-' }} | NIS: {{ $student->nis }}</p>
    @if ($reports->isEmpty())
        <p style="color: #7f8c8d; margin-top: 16px;">Belum ada laporan. <a href="{{ route('siswa.reports.create') }}">Buat Laporan</a></p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 16px;">
            <thead><tr><th>No</th><th>Judul</th><th>Kategori</th><th>Ringkasan</th><th>Status</th><th>Foto</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($reports as $report)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->category?->name }} ({{ $report->priority }})</td>
                        <td style="max-width: 240px;">{{ \Illuminate\Support\Str::limit($report->description, 80) }}<br><small style="color: #7f8c8d;">{{ $report->schoolClass?->name }} - {{ $report->location }}</small></td>
                        <td>
                            @if ($report->status === 'menunggu')
                                <span class="badge badge-menunggu">Menunggu</span>
                            @elseif ($report->status === 'selesai')
                                <span class="badge badge-selesai">Selesai</span>
                            @else
                                <span class="badge badge-proses">{{ $report->status }}</span>
                            @endif
                        </td>
                        <td>{{ $report->attachments_count }}</td>
                        <td>{{ $report->created_at?->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('siswa.reports.show', $report) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
@endsection
