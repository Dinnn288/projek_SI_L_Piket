@extends('layouts.admin')

@section('title', 'Tambah Kelas')

@section('content')
<div class="welcome-card">
    <h1>Tambah Kelas</h1>
    <form method="POST" action="{{ route('admin.classes.store') }}" style="margin-top: 24px;">
        @csrf
        <div style="margin-bottom: 16px;">
            <label for="name">Nama Kelas</label><br>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="255" style="width: 100%; margin-top: 6px; padding: 8px;">
        </div>
        <div style="margin-bottom: 16px;">
            <label for="major_id">Jurusan</label><br>
            <select id="major_id" name="major_id" required style="width: 100%; margin-top: 6px; padding: 8px;">
                <option value="">Pilih Jurusan</option>
                @foreach ($majors as $major)
                    <option value="{{ $major->id }}" @selected((string) old('major_id') === (string) $major->id)>{{ $major->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="margin-bottom: 16px;">
            <label for="status">Status</label><br>
            <select id="status" name="status" required style="width: 100%; margin-top: 6px; padding: 8px;">
                <option value="aktif" @selected(old('status', 'aktif') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status') === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Simpan</button>
        <a href="{{ route('admin.classes.index') }}" style="margin-left: 12px;">Batal</a>
    </form>
</div>
@endsection
