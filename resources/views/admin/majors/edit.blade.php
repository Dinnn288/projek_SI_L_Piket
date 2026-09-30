@extends('layouts.admin')

@section('title', 'Edit Jurusan')

@section('content')
<div class="welcome-card">
    <h1>Edit Jurusan</h1>
    <form method="POST" action="{{ route('admin.majors.update', $major) }}" style="margin-top: 24px;">
        @csrf
        @method('PUT')
        <div style="margin-bottom: 16px;">
            <label for="name">Nama Jurusan</label><br>
            <input id="name" type="text" name="name" value="{{ old('name', $major->name) }}" required maxlength="200" style="width: 100%; margin-top: 6px; padding: 8px;">
        </div>
        <div style="margin-bottom: 16px;">
            <label for="status">Status</label><br>
            <select id="status" name="status" required style="width: 100%; margin-top: 6px; padding: 8px;">
                <option value="aktif" @selected(old('status', $major->status) === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status', $major->status) === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Simpan</button>
        <a href="{{ route('admin.majors.index') }}" style="margin-left: 12px;">Batal</a>
    </form>
</div>
@endsection
