@extends('layouts.admin')

@section('title', 'Edit Kelas')

@section('content')
<div class="sl-card">
    <h1>Edit Kelas</h1>
    <form method="POST" action="{{ route('admin.classes.update', $schoolClass) }}" class="mt-3">
        @csrf
        @method('PUT')
        <div class="mt-2">
            <label for="name">Nama Kelas</label>
            <input id="name" type="text" name="name" value="{{ old('name', $schoolClass->name) }}" required maxlength="255">
        </div>
        <div class="mt-2">
            <label for="major_id">Jurusan</label>
            <select id="major_id" name="major_id" required>
                <option value="">Pilih Jurusan</option>
                @foreach ($majors as $major)
                    <option value="{{ $major->id }}" @selected((string) old('major_id', $schoolClass->major_id) === (string) $major->id)>{{ $major->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mt-2">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <option value="aktif" @selected(old('status', $schoolClass->status) === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status', $schoolClass->status) === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.classes.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


