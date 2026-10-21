@extends('layouts.siswa')

@section('title', 'Piket Kelas Saya')

@section('content')
<div class="sl-card">
    <h1 style="font-size: 22px; color: #1f3d2b;">Piket Kelas Saya</h1>
    <p class="muted text-sm mt-1">Kelas: {{ $student->schoolClass?->name ?? '-' }} ({{ $student->schoolClass?->major?->name ?? '-' }}) | Tanggal: {{ $date }} ({{ $day ?? 'minggu' }})</p>
    <p class="muted text-sm mt-1">Jadwal hari ini:
        @if ($scheduled)
            <span class="badge badge-aktif">Terjadwal</span>
        @else
            <span class="badge badge-nonaktif">Tidak dijadwalkan</span>
        @endif
        | Status:
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
        <p style="margin-top: 12px; color: #7f8c8d;">Kelas Anda tidak dijadwalkan piket hari ini.</p>
    @elseif (! $report)
        <div style="margin-top: 14px;">
            <a href="{{ route('siswa.class-duty.create') }}" class="btn">Kirim Laporan Piket</a>
        </div>
    @endif
</div>

@if ($report)
    <div class="sl-card">
        <h2 style="font-size: 18px;">Laporan hari ini</h2>
        <p class="muted text-sm mt-1">Dikirim: {{ $report->created_at?->format('d M Y H:i') }} WIB oleh {{ $report->student?->userBaru?->name ?? '-' }}</p>
        @if ($report->status === 'perlu_diperbaiki')
            <p style="margin-top: 10px; color: #b91c1c;">Catatan perbaikan dari Guru Piket: {{ $report->return_note }}</p>
        @endif
        <div class="mt-2">
            <h3 style="font-size: 15px;">Checklist</h3>
            <ul style="margin-top: 8px; padding-left: 20px;">
                @foreach ((array) $report->checklist as $item)
                    <li>{{ \App\Services\ClassDutyService::CHECKLIST_ITEMS[$item] ?? $item }}</li>
                @endforeach
            </ul>
        </div>
        @if ($report->notes)
            <p class="mt-1" style="white-space: pre-line;">{{ $report->notes }}</p>
        @endif
        @if ($report->attachments->isNotEmpty())
            <div style="margin-top: 12px;">
                <h3 style="font-size: 15px;">Foto bukti ({{ $report->attachments->count() }})</h3>
                <div class="photo-grid">
                    @foreach ($report->attachments as $attachment)
                        <div>
                            <img src="{{ route('siswa.class-duty.attachment', [$report, $attachment]) }}" alt="{{ $attachment->original_name }}" loading="lazy">
                            <div class="caption">{{ $attachment->original_name }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        <div class="mt-2">
            <h3 style="font-size: 15px;">Riwayat</h3>
            <ul class="timeline">
                @foreach ($report->histories->sortBy('created_at') as $history)
                    <li><strong>{{ $history->created_at?->format('d M Y H:i') }}</strong> — {{ $history->userBaru?->name }}: {{ $history->action }} ({{ $history->from_status ?? '-' }} → {{ $history->to_status ?? '-' }}){{ $history->note ? ' — '.$history->note : '' }}</li>
                @endforeach
            </ul>
        </div>
        @if ($report->status === 'perlu_diperbaiki')
            <form method="POST" action="{{ route('siswa.class-duty.resubmit', $report) }}" enctype="multipart/form-data" class="mt-3">
                @csrf
                <h3 style="font-size: 15px;">Perbaiki dan kirim ulang (tanggal yang sama)</h3>
                <div class="mt-2">
                    @foreach (\App\Services\ClassDutyService::CHECKLIST_ITEMS as $key => $label)
                        <label style="display: block; margin-bottom: 6px;"><input type="checkbox" name="checklist[]" value="{{ $key }}" @checked(in_array($key, (array) $report->checklist))> {{ $label }}</label>
                    @endforeach
                </div>
                <div class="mt-2">
                    <label for="notes">Catatan / keterangan</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="2000">{{ old('notes', $report->notes) }}</textarea>
                </div>
                <div class="mt-2">
                    <label for="photos">Tambah foto (opsional, maks 5, JPG/JPEG/PNG/WEBP 8 MB)</label>
                    <input id="photos" type="file" name="photos[]" accept="image/*" multiple>
                </div>
                <button type="submit" class="btn" style="margin-top: 12px;">Kirim Ulang</button>
            </form>
        @endif
    </div>
@endif
@endsection
