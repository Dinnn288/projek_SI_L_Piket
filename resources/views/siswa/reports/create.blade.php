@extends('layouts.siswa')

@section('title', 'Buat Laporan')

@section('content')
<div class="card">
    <h1 style="font-size: 22px; color: #1f3d2b;">Buat Laporan</h1>
    <p style="color: #5d6d7e; margin-top: 6px;">Lokasi: {{ $student->schoolClass?->name ?? '-' }} (otomatis dari data kelas, tidak dapat diubah)</p>
    <form method="POST" action="{{ route('siswa.reports.store') }}" enctype="multipart/form-data" style="margin-top: 18px;">
        @csrf
        <div style="margin-bottom: 14px;">
            <label for="title">Judul</label>
            <input id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="200">
        </div>
        <div style="margin-bottom: 14px;">
            <label for="category_id">Kategori</label>
            <select id="category_id" name="category_id" required>
                <option value="">Pilih Kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }} ({{ $category->priority }})</option>
                @endforeach
            </select>
        </div>
        <div style="margin-bottom: 14px;">
            <label for="location">Lokasi detail</label>
            <input id="location" type="text" name="location" value="{{ old('location', $student->schoolClass?->name) }}" required maxlength="200" placeholder="Contoh: {{ $student->schoolClass?->name }} - depan papan tulis">
        </div>
        <div style="margin-bottom: 14px;">
            <label for="description">Deskripsi / Isi Laporan</label>
            <textarea id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
        </div>
        <div style="margin-bottom: 14px;">
            <label for="photos">Foto bukti (wajib, 1-5 foto, maks 8 MB per foto: jpg/jpeg/png/webp)</label>
            <input id="photos" type="file" name="photos[]" accept="image/*" multiple required style="margin-top: 6px;">
        </div>
        <button type="submit" class="btn">Kirim Laporan</button>
        <a href="{{ route('siswa.reports.index') }}" class="btn btn-secondary" style="margin-left: 10px;">Batal</a>
    </form>
</div>
@endsection
