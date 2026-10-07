@extends('layouts.kepala-sekolah')

@section('title', 'Detail Laporan Diteruskan')

@section('content')
<div class="sl-card">
    @if (session('success'))
        <p style="margin-bottom: 12px; color: #1e8449; font-weight: 600;">{{ session('success') }}</p>
    @endif
    @if (session('error'))
        <p style="margin-bottom: 12px; color: #c0392b; font-weight: 600;">{{ session('error') }}</p>
    @endif
    @if (isset($errors) && $errors->any())
        <ul style="margin-bottom: 12px; padding-left: 20px; color: #c0392b;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif
    <div class="row-between" style="align-items:flex-start;">
        <h1 style="font-size: 22px;">{{ $report->title }}</h1>
        <x-status-badge :value="$report->status" />
    </div>
    <h2 style="font-size: 16px; margin-top: 14px;">Informasi Siswa</h2>
    <p class="muted text-sm mt-1">Nama: {{ $report->student?->userBaru?->name }} | Kelas: {{ $report->schoolClass?->name }} | Lokasi: {{ $report->location }}</p>
    <h2 style="font-size: 16px; margin-top: 14px;">Informasi Laporan</h2>
    <p class="muted text-sm mt-1">Kategori: {{ $report->category?->name }} | Prioritas: <x-priority-badge :value="$report->priority" /> | Status: <x-status-badge :value="$report->status" /> | Dibuat: {{ $report->created_at?->format('d M Y H:i') }}</p>
    <h2 style="font-size: 16px; margin-top: 14px;">Deskripsi Siswa</h2>
    <p class="mt-1" style="white-space: pre-line;">{{ $report->description }}</p>
    <h2 style="font-size: 16px; margin-top: 14px;">Alasan Guru Meneruskan</h2>
    <p class="mt-1" style="white-space: pre-line;">{{ $forwardHistory?->note ?? '-' }}</p>
    <p class="muted text-sm mt-1">Guru penerus: {{ $forwardHistory?->userBaru?->name ?? $report->assignedTeacher?->userBaru?->name }} | Waktu forward: {{ $forwardHistory?->created_at?->format('d M Y H:i') }}</p>
    <h2 style="font-size: 16px; margin-top: 14px;">Bukti foto ({{ $report->attachments->count() }})</h2>
    <div class="photo-grid">
        @foreach ($report->attachments as $attachment)
            <div>
                    <img src="{{ route('kepala-sekolah.reports.attachment', [$report, $attachment]) }}" alt="{{ $attachment->original_name }}" loading="lazy">
                <div class="caption">{{ $attachment->original_name }} ({{ number_format($attachment->file_size / 1024, 1) }} KB)</div>
            </div>
        @endforeach
    </div>
    <h2 style="font-size: 16px; margin-top: 16px;">Riwayat Laporan</h2>
    <ul class="timeline">
        @foreach ($report->histories->sortBy('created_at') as $history)
            <li><strong>{{ $history->created_at?->format('d M Y H:i') }}</strong> — {{ $history->userBaru?->name }}: {{ $history->action }} ({{ $history->from_status ?? '-' }} → {{ $history->to_status ?? '-' }}){{ $history->note ? ' — '.$history->note : '' }}</li>
        @endforeach
    </ul>
    @if (($canAct ?? ($report->status === 'diteruskan')))
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 18px;">
            <form method="POST" action="{{ route('kepala-sekolah.reports.complete', $report) }}" onsubmit="return confirm('Selesaikan laporan ini?');">
                @csrf
                <div style="margin-bottom: 10px;">
                    <label for="complete_note">Catatan penyelesaian (wajib)</label>
                    <textarea id="complete_note" name="note" rows="3" required maxlength="2000" placeholder="Contoh: Sudah ditindaklanjuti, terima kasih.">{{ old('note') }}</textarea>
                </div>
                <button type="submit" class="btn">Selesaikan Laporan</button>
            </form>
            <form method="POST" action="{{ route('kepala-sekolah.reports.return', $report) }}" onsubmit="return confirm('Kembalikan laporan ini ke Guru Piket?');">
                @csrf
                <div style="margin-bottom: 10px;">
                    <label for="return_note">Alasan pengembalian ke Guru (wajib)</label>
                    <textarea id="return_note" name="note" rows="3" required maxlength="2000" placeholder="Contoh: Mohon lengkapi penanganan awal terlebih dahulu.">{{ old('note') }}</textarea>
                </div>
                <button type="submit" class="btn btn-secondary">Kembalikan ke Guru</button>
            </form>
        </div>
    @else
        <p style="color: #5d6d7e; font-weight: 600; margin-top: 18px;">Laporan ini sudah selesai dan tidak dapat diproses lagi.</p>
    @endif
    <div style="margin-top: 18px;">
        <a href="{{ route('kepala-sekolah.reports.index') }}" class="btn btn-secondary">Kembali</a>
        <a href="{{ route('kepala-sekolah.dashboard') }}" class="btn" style="margin-left: 10px;">Dashboard</a>
    </div>
</div>
@endsection

