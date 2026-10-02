@extends('layouts.admin')

@section('title', 'Edit Jurusan')

@section('content')
<div class="sl-card">
    <h1>Edit Jurusan</h1>
    <form method="POST" action="{{ route('admin.majors.update', $major) }}" class="mt-3">
        @csrf
        @method('PUT')
        <div class="mt-2">
            <label for="name">Nama Jurusan</label>
            <input id="name" type="text" name="name" value="{{ old('name', $major->name) }}" required maxlength="200">
        </div>
        <div class="mt-2">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <option value="aktif" @selected(old('status', $major->status) === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status', $major->status) === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.majors.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


