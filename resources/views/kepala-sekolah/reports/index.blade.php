@extends('layouts.kepala-sekolah')

@section('title', 'Laporan Diteruskan')

@section('content')
<div class="card">
    <h1 style="font-size: 22px; color: #4a235a;">Laporan Diteruskan</h1>
    <p style="color: #5d6d7e; margin-top: 6px;">Hanya laporan berstatus diteruskan. Bersifat read-only.</p>
    @if ($reports->isEmpty())
        <p style="color: #7f8c8d; margin-top: 14px;">Belum ada laporan yang diteruskan.</p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 14px;">
            <thead><tr><th>No</th><th>Judul</th><th>Pelapor / Kelas</th><th>Kategori</th><th>Guru penerus</th><th>Alasan penerusan</th><th>Foto</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($reports as $report)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->student?->userBaru?->name }} / {{ $report->schoolClass?->name }}</td>
                        <td>{{ $report->category?->name }} ({{ $report->priority }})</td>
                        <td>{{ $report->assignedTeacher?->userBaru?->name }}</td>
                        <td style="max-width: 240px;">{{ \Illuminate\Support\Str::limit($report->histories->first()?->note ?? '-', 100) }}</td>
                        <td>{{ $report->attachments->count() }}</td>
                        <td>{{ $report->created_at?->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('kepala-sekolah.reports.show', $report) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>
@endsection
