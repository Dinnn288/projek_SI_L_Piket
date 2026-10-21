@extends('layouts.siswa')

@section('title', 'Kirim Laporan Piket')

@section('content')
<div class="sl-card">
    <h1 style="font-size: 22px; color: #1f3d2b;">Kirim Laporan Piket</h1>
    <p class="muted text-sm mt-1">Kelas: {{ $student->schoolClass?->name ?? '-' }} (otomatis dari data akun, tidak dapat diubah) | Tanggal: {{ $date }} ({{ $day }})</p>
    <p class="muted text-sm mt-1">Satu kelas satu tanggal hanya satu laporan. Jika laporan sudah ada, kirim ulang lewat halaman status, bukan laporan baru.</p>
    <form method="POST" action="{{ route('siswa.class-duty.store') }}" enctype="multipart/form-data" class="mt-3">
        @csrf
        <div class="mt-2">
            <h3 style="font-size: 15px;">Checklist pelaksanaan (wajib, minimal 1)</h3>
            @foreach ($checklistItems as $key => $label)
                <label style="display: block; margin-bottom: 6px;"><input type="checkbox" name="checklist[]" value="{{ $key }}" @checked(in_array($key, (array) old('checklist', [])))> {{ $label }}</label>
            @endforeach
        </div>
        <div class="mt-2">
            <label for="notes">Catatan / keterangan</label>
            <textarea id="notes" name="notes" rows="4" maxlength="2000" placeholder="Contoh: Kelas disapu dan dipel, sampah dibuang, meja dirapikan.">{{ old('notes') }}</textarea>
        </div>
        <div class="mt-2">
            <label for="photos">Foto bukti (opsional, maks 5, JPG/JPEG/PNG/WEBP 8 MB per foto)</label>
            <input id="photos" type="file" name="photos[]" accept="image/*" multiple>
            <p class="muted text-sm mt-1">Kamera membutuhkan HTTPS/localhost; jika kamera tidak tersedia, pilih dari galeri.</p>
        </div>
        <button type="submit" class="btn" style="margin-top: 12px;">Kirim Laporan</button>
        <a href="{{ route('siswa.class-duty.index') }}" class="btn btn-secondary" style="margin-top: 12px;">Batal</a>
    </form>
</div>
@endsection
