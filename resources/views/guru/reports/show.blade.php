@extends('layouts.guru')

@section('title', 'Detail Laporan Piket')

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; flex-wrap: wrap;">
        <h1 style="font-size: 22px; color: #1a3d5c;">{{ $report->title }}</h1>
        @if ($report->status === 'menunggu')
            <span class="badge badge-menunggu">Menunggu</span>
        @elseif ($report->status === 'selesai')
            <span class="badge badge-selesai">Selesai</span>
        @else
            <span class="badge badge-proses">{{ $report->status }}</span>
        @endif
    </div>
    <p style="color: #5d6d7e; margin-top: 6px;">Kategori: {{ $report->category?->name }} | Prioritas: {{ $report->priority }}</p>
    <p style="color: #5d6d7e; margin-top: 6px;">Pelapor: {{ $report->student?->userBaru?->name }} | Kelas: {{ $report->schoolClass?->name }} | Lokasi: {{ $report->location }}</p>
    <p style="color: #5d6d7e; margin-top: 6px;">Dibuat: {{ $report->created_at?->format('d M Y H:i') }} | Penanggung jawab: {{ $report->assignedTeacher?->userBaru?->name ?? 'Belum diambil' }}</p>
    <div style="margin-top: 16px;">
        <h2 style="font-size: 16px;">Deskripsi</h2>
        <p style="margin-top: 6px; white-space: pre-line;">{{ $report->description }}</p>
    </div>
    <div style="margin-top: 16px;">
        <h2 style="font-size: 16px;">Foto bukti ({{ $report->attachments->count() }})</h2>
        <div class="photo-grid">
            @foreach ($report->attachments as $attachment)
                <div>
                    <img src="{{ route('guru.reports.attachment', [$report, $attachment]) }}" alt="{{ $attachment->original_name }}" loading="lazy">
                    <div class="caption">{{ $attachment->original_name }} ({{ number_format($attachment->file_size / 1024, 1) }} KB)</div>
                </div>
            @endforeach
        </div>
    </div>
    <div style="margin-top: 16px;">
        <h2 style="font-size: 16px;">Riwayat penanganan</h2>
        @if ($report->histories->isEmpty())
            <p style="color: #7f8c8d; margin-top: 6px;">Belum ada riwayat.</p>
        @else
            <ul style="margin-top: 8px; padding-left: 20px;">
                @foreach ($report->histories->sortBy('created_at') as $history)
                    <li>{{ $history->created_at?->format('d M Y H:i') }} - {{ $history->userBaru?->name }}: {{ $history->action }} ({{ $history->from_status ?? '-' }} → {{ $history->to_status ?? '-' }}){{ $history->note ? ' - '.$history->note : '' }}</li>
                @endforeach
            </ul>
        @endif
    </div>
    <div style="margin-top: 18px;">
        @if ($isWaiting && $canClaim)
            <form method="POST" action="{{ route('guru.reports.claim', $report) }}" onsubmit="return confirm('Ambil laporan ini?');">
                @csrf
                <button type="submit" class="btn">Ambil Laporan</button>
            </form>
        @elseif ($isWaiting && ! $canClaim)
            <p style="color: #b7950b; font-weight: 600;">{{ $claimMessage }}</p>
        @elseif (($isContinuable ?? false) && ($canTakeover ?? false))
            <p style="color: #5d6d7e; margin-top: 6px;">Lanjutan dari hari sebelumnya. Guru sebelumnya: {{ $report->assignedTeacher?->userBaru?->name }} (diambil {{ $report->assigned_at?->format('d M Y H:i') }}).</p>
            <form method="POST" action="{{ route('guru.reports.takeover', $report) }}" onsubmit="return confirm('Ambil alih laporan ini?');" style="margin-top: 12px;">
                @csrf
                <button type="submit" class="btn">Ambil Alih Laporan</button>
            </form>
        @elseif ($isContinuable ?? false)
            <p style="color: #5d6d7e; margin-top: 6px;">Lanjutan dari hari sebelumnya. Guru sebelumnya: {{ $report->assignedTeacher?->userBaru?->name }} (diambil {{ $report->assigned_at?->format('d M Y H:i') }}).</p>
            <p style="color: #b7950b; font-weight: 600; margin-top: 10px;">{{ $takeoverMessage ?? '' }}</p>
        @endif
        @if ($isMine && $report->status === 'dalam_penanganan')
            <p style="color: #1e8449; font-weight: 600;">Status: Dalam Penanganan. Penanggung jawab: Anda.</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 14px;">
                <form method="POST" action="{{ route('guru.reports.complete', $report) }}" onsubmit="return confirm('Selesaikan laporan ini?');">
                    @csrf
                    <div style="margin-bottom: 10px;">
                        <label for="complete_note">Catatan penyelesaian (wajib)</label>
                        <textarea id="complete_note" name="note" rows="3" required maxlength="2000" placeholder="Contoh: Lampu sudah diganti dan menyala kembali.">{{ old('note') }}</textarea>
                    </div>
                    <button type="submit" class="btn">Selesaikan Laporan</button>
                </form>
                <form method="POST" action="{{ route('guru.reports.forward', $report) }}" onsubmit="return confirm('Teruskan laporan ini ke Kepala Sekolah?');">
                    @csrf
                    <div style="margin-bottom: 10px;">
                        <label for="forward_note">Alasan diteruskan ke Kepala Sekolah (wajib)</label>
                        <textarea id="forward_note" name="note" rows="3" required maxlength="2000" placeholder="Contoh: Kerusakan berat, perlu persetujuan pengadaan.">{{ old('note') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary">Teruskan ke Kepala Sekolah</button>
                </form>
            </div>
        @elseif ($isMine)
            <p style="color: #5d6d7e; font-weight: 600;">Laporan ini sudah {{ $report->status }} dan tidak dapat diproses lagi.</p>
        @endif
        <div style="margin-top: 14px;">
            <a href="{{ route('guru.reports.index') }}" class="btn btn-secondary">Kembali</a>
            <a href="{{ route('guru.dashboard') }}" class="btn" style="margin-left: 10px;">Dashboard</a>
        </div>
    </div>
</div>
@endsection
