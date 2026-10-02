@extends('layouts.admin')

@section('title', 'Tambah Kategori')

@section('content')
<div class="sl-card">
    <h1>Tambah Kategori</h1>
    <form method="POST" action="{{ route('admin.report-categories.store') }}" class="mt-3">
        @csrf
        <div class="mt-2">
            <label for="name">Nama Kategori</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="200">
        </div>
        <div class="mt-2">
            <label for="description">Deskripsi (opsional)</label>
            <textarea id="description" name="description" rows="3">{{ old('description') }}</textarea>
        </div>
        <div class="mt-2">
            <label for="priority">Prioritas</label>
            <select id="priority" name="priority" required>
                <option value="tinggi" @selected(old('priority', 'tinggi') === 'tinggi')>Tinggi</option>
                <option value="sedang" @selected(old('priority') === 'sedang')>Sedang</option>
                <option value="rendah" @selected(old('priority') === 'rendah')>Rendah</option>
            </select>
        </div>
        <div class="mt-2">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <option value="aktif" @selected(old('status', 'aktif') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status') === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.report-categories.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


