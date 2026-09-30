@extends('layouts.admin')

@section('title', 'Edit Kategori')

@section('content')
<div class="welcome-card">
    <h1>Edit Kategori</h1>
    <form method="POST" action="{{ route('admin.report-categories.update', $category) }}" style="margin-top: 24px;">
        @csrf
        @method('PUT')
        <div style="margin-bottom: 16px;">
            <label for="name">Nama Kategori</label><br>
            <input id="name" type="text" name="name" value="{{ old('name', $category->name) }}" required maxlength="200" style="width: 100%; margin-top: 6px; padding: 8px;">
        </div>
        <div style="margin-bottom: 16px;">
            <label for="description">Deskripsi (opsional)</label><br>
            <textarea id="description" name="description" rows="3" style="width: 100%; margin-top: 6px; padding: 8px;">{{ old('description', $category->description) }}</textarea>
        </div>
        <div style="margin-bottom: 16px;">
            <label for="priority">Prioritas</label><br>
            <select id="priority" name="priority" required style="width: 100%; margin-top: 6px; padding: 8px;">
                <option value="tinggi" @selected(old('priority', $category->priority) === 'tinggi')>Tinggi</option>
                <option value="sedang" @selected(old('priority', $category->priority) === 'sedang')>Sedang</option>
                <option value="rendah" @selected(old('priority', $category->priority) === 'rendah')>Rendah</option>
            </select>
        </div>
        <div style="margin-bottom: 16px;">
            <label for="status">Status</label><br>
            <select id="status" name="status" required style="width: 100%; margin-top: 6px; padding: 8px;">
                <option value="aktif" @selected(old('status', $category->status) === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status', $category->status) === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Simpan</button>
        <a href="{{ route('admin.report-categories.index') }}" style="margin-left: 12px;">Batal</a>
    </form>
</div>
@endsection
