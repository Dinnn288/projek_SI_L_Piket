@extends('layouts.admin')

@section('title', 'Tambah Kelas')

@section('content')
<div class="sl-card">
    <h1>Tambah Kelas</h1>
    <form method="POST" action="{{ route('admin.classes.store') }}" class="mt-3">
        @csrf
        <div class="mt-2">
            <label for="name">Nama Kelas</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="255">
        </div>
        <div class="mt-2">
            <label for="major_id">Jurusan</label>
            <select id="major_id" name="major_id" required>
                <option value="">Pilih Jurusan</option>
                @foreach ($majors as $major)
                    <option value="{{ $major->id }}" @selected((string) old('major_id') === (string) $major->id)>{{ $major->name }}</option>
                @endforeach
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
        <a href="{{ route('admin.classes.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


