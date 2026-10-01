@extends('layouts.guru')

@section('title', 'Laporan Piket')

@section('content')
<div class="card">
    <h1 style="font-size: 22px; color: #1a3d5c;">Laporan Menunggu</h1>
    <p style="color: #5d6d7e; margin-top: 6px;">Hari ini: {{ $day ?? '-' }} | Status piket: {{ $onDutyDay ? 'Bertugas' : 'Tidak bertugas' }} | Jam piket: 07.00-16.00</p>
    @if (! $onDutyDay)
        <p style="color: #b7950b; margin-top: 10px;">Daftar disembunyikan karena Anda tidak terjadwal piket hari ini.</p>
    @elseif ($waiting->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Tidak ada laporan menunggu.</p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 14px;">
            <thead><tr><th>No</th><th>Judul</th><th>Kategori</th><th>Pelapor / Kelas</th><th>Foto</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($waiting as $report)
                    <tr>
                        <td>{{ ($waiting->firstItem() ?? 0) + $loop->index }}</td>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->category?->name }} ({{ $report->priority }})</td>
                        <td>{{ $report->student?->userBaru?->name }} / {{ $report->schoolClass?->name }}</td>
                        <td>{{ $report->attachments_count }}</td>
                        <td>{{ $report->created_at?->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('guru.reports.show', $report) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <x-pagination :paginator="$waiting" />
    @endif
</div>

<div class="card" style="margin-top: 20px;">
    <h1 style="font-size: 22px; color: #1a3d5c;">Lanjutan dari Hari Sebelumnya</h1>
    <p style="color: #5d6d7e; margin-top: 6px;">Laporan dalam penanganan yang dapat diambil alih Guru Piket hari ini. Riwayat guru sebelumnya tetap tersimpan.</p>
    @if (! $onDutyDay)
        <p style="color: #b7950b; margin-top: 10px;">Daftar disembunyikan karena Anda tidak terjadwal piket hari ini.</p>
    @elseif ($continued->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Tidak ada laporan lanjutan.</p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 14px;">
            <thead><tr><th>No</th><th>Judul</th><th>Kategori</th><th>Pelapor / Kelas</th><th>Guru sebelumnya</th><th>Foto</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($continued as $report)
                    <tr>
                        <td>{{ ($continued->firstItem() ?? 0) + $loop->index }}</td>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->category?->name }} ({{ $report->priority }})</td>
                        <td>{{ $report->student?->userBaru?->name }} / {{ $report->schoolClass?->name }}</td>
                        <td>{{ $report->assignedTeacher?->userBaru?->name }}</td>
                        <td>{{ $report->attachments_count }}</td>
                        <td><a href="{{ route('guru.reports.show', $report) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <x-pagination :paginator="$continued" />
    @endif
</div>

<div class="card" style="margin-top: 20px;">
    <h1 style="font-size: 22px; color: #1a3d5c;">Laporan Yang Saya Tangani</h1>
    @if ($mine->isEmpty())
        <p style="color: #7f8c8d; margin-top: 10px;">Belum ada laporan yang Anda ambil.</p>
    @else
        <div class="table-wrap">
        <table style="margin-top: 14px;">
            <thead><tr><th>No</th><th>Judul</th><th>Kategori</th><th>Status</th><th>Foto</th><th>Diambil</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach ($mine as $report)
                    <tr>
                        <td>{{ ($mine->firstItem() ?? 0) + $loop->index }}</td>
                        <td>{{ $report->title }}</td>
                        <td>{{ $report->category?->name }}</td>
                        <td><span class="badge badge-proses">{{ $report->status }}</span></td>
                        <td>{{ $report->attachments_count }}</td>
                        <td>{{ $report->assigned_at?->format('d M Y H:i') }}</td>
                        <td><a href="{{ route('guru.reports.show', $report) }}">Detail</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <x-pagination :paginator="$mine" />
    @endif
</div>
@endsection
