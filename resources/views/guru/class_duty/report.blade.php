@extends('layouts.guru')

@section('title', 'Laporan Piket Kelas')

@section('content')
<div class="sl-card">
    <div class="row-between" style="align-items:flex-start;">
        <h1 style="font-size: 22px;">{{ $classDutyReport->schoolClass?->name }} — {{ $classDutyReport->report_date?->format('d M Y') }}</h1>
        @if ($classDutyReport->status === 'disetujui')
            <span class="badge badge-selesai">Sudah piket</span>
        @elseif ($classDutyReport->status === 'perlu_diperbaiki')
            <span class="badge badge-forward">Perlu diperbaiki</span>
        @else
            <span class="badge badge-menunggu">Menunggu verifikasi</span>
        @endif
    </div>
    <p class="muted text-sm mt-1">Perwakilan: {{ $classDutyReport->student?->userBaru?->name }} ({{ $classDutyReport->student?->nis }}) | Dikirim: {{ $classDutyReport->created_at?->format('d M Y H:i') }} WIB</p>
    @if ($classDutyReport->status === 'disetujui')
        <p class="muted text-sm mt-1">Disetujui oleh {{ $classDutyReport->verifiedBy?->userBaru?->name ?? '-' }} pada {{ $classDutyReport->verified_at?->format('d M Y H:i') }} WIB</p>
    @endif
    @if ($classDutyReport->status === 'perlu_diperbaiki')
        <p style="margin-top: 10px; color: #b91c1c;">Catatan pengembalian: {{ $classDutyReport->return_note }}</p>
    @endif

    <div class="mt-2">
        <h2 style="font-size: 16px;">Checklist pelaksanaan</h2>
        <ul style="margin-top: 8px; padding-left: 20px;">
            @foreach ((array) $classDutyReport->checklist as $item)
                <li>{{ \App\Services\ClassDutyService::CHECKLIST_ITEMS[$item] ?? $item }}</li>
            @endforeach
        </ul>
    </div>
    @if ($classDutyReport->notes)
        <div class="mt-2">
            <h2 style="font-size: 16px;">Catatan</h2>
            <p class="mt-1" style="white-space: pre-line;">{{ $classDutyReport->notes }}</p>
        </div>
    @endif
    <div style="margin-top: 16px;">
        <h2 style="font-size: 16px;">Foto bukti ({{ $classDutyReport->attachments->count() }})</h2>
        @if ($classDutyReport->attachments->isEmpty())
            <p class="empty">Tidak ada foto.</p>
        @else
            <div class="photo-grid">
                @foreach ($classDutyReport->attachments as $attachment)
                    <div>
                        <img src="{{ route('guru.class-duty.attachment', [$classDutyReport, $attachment]) }}" alt="{{ $attachment->original_name }}" loading="lazy">
                        <div class="caption">{{ $attachment->original_name }} ({{ number_format($attachment->file_size / 1024, 1) }} KB)</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    <div class="mt-2">
        <h2 style="font-size: 16px;">Riwayat keputusan</h2>
        @if ($classDutyReport->histories->isEmpty())
            <p class="empty">Belum ada riwayat.</p>
        @else
            <ul class="timeline">
                @foreach ($classDutyReport->histories->sortBy('created_at') as $history)
                    <li><strong>{{ $history->created_at?->format('d M Y H:i') }}</strong> — {{ $history->userBaru?->name }}: {{ $history->action }} ({{ $history->from_status ?? '-' }} → {{ $history->to_status ?? '-' }}){{ $history->note ? ' — '.$history->note : '' }}</li>
                @endforeach
            </ul>
        @endif
    </div>
    <div style="margin-top: 18px; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
        <form method="POST" action="{{ route('guru.class-duty.approve', $classDutyReport) }}" onsubmit="return confirm('Setujui laporan ini?');">
            @csrf
            <div style="margin-bottom: 10px;">
                <label for="approve_note">Catatan persetujuan (wajib)</label>
                <textarea id="approve_note" name="note" rows="3" required maxlength="2000" placeholder="Contoh: Pelaksanaan lengkap dan bersih.">{{ old('note') }}</textarea>
            </div>
            <button type="submit" class="btn">Setujui Laporan</button>
        </form>
        <form method="POST" action="{{ route('guru.class-duty.return', $classDutyReport) }}" onsubmit="return confirm('Kembalikan laporan ini?');">
            @csrf
            <div style="margin-bottom: 10px;">
                <label for="return_note">Alasan pengembalian (wajib)</label>
                <textarea id="return_note" name="note" rows="3" required maxlength="2000" placeholder="Contoh: Foto papan tulis belum ada, lengkapi lalu kirim ulang.">{{ old('note') }}</textarea>
            </div>
            <button type="submit" class="btn btn-secondary">Kembalikan</button>
        </form>
    </div>
    <div style="margin-top: 14px;">
        <a href="{{ route('guru.class-duty.index') }}" class="btn btn-secondary">Kembali ke daftar kelas</a>
    </div>
</div>
@endsection
