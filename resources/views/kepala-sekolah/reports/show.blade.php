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
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; flex-wrap: wrap;">
        <h1 style="font-size: 22px; color: #4a235a;">{{ $report->title }}</h1>
        @if ($report->status === 'diteruskan')
            <span class="badge badge-forward">Diteruskan</span>
        @else
            <span class="badge badge-done">Selesai</span>
        @endif
    </div>
    <h2 style="font-size: 16px; margin-top: 14px;">Informasi Siswa</h2>
    <p style="color: #5d6d7e; margin-top: 6px;">Nama: {{ $report->student?->userBaru?->name }} | Kelas: {{ $report->schoolClass?->name }} | Lokasi: {{ $report->location }}</p>
    <h2 style="font-size: 16px; margin-top: 14px;">Informasi Laporan</h2>
    <p style="color: #5d6d7e; margin-top: 6px;">Kategori: {{ $report->category?->name }} | Prioritas: {{ $report->priority }} | Status: {{ $report->status }} | Dibuat: {{ $report->created_at?->format('d M Y H:i') }}</p>
    <h2 style="font-size: 16px; margin-top: 14px;">Deskripsi Siswa</h2>
    <p style="margin-top: 6px; white-space: pre-line;">{{ $report->description }}</p>
    <h2 style="font-size: 16px; margin-top: 14px;">Alasan Guru Meneruskan</h2>
    <p style="margin-top: 6px; white-space: pre-line;">{{ $forwardHistory?->note ?? '-' }}</p>
    <p style="color: #5d6d7e; margin-top: 6px;">Guru penerus: {{ $forwardHistory?->userBaru?->name ?? $report->assignedTeacher?->userBaru?->name }} | Waktu forward: {{ $forwardHistory?->created_at?->format('d M Y H:i') }}</p>
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
    <ul style="margin-top: 8px; padding-left: 20px;">
        @foreach ($report->histories->sortBy('created_at') as $history)
            <li>{{ $history->created_at?->format('d M Y H:i') }} - {{ $history->userBaru?->name }}: {{ $history->action }} ({{ $history->from_status ?? '-' }} → {{ $history->to_status ?? '-' }}){{ $history->note ? ' - '.$history->note : '' }}</li>
        @endforeach
    </ul>
    @if ($canAct)
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

