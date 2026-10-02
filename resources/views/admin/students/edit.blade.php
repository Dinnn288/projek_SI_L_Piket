@extends('layouts.admin')

@section('title', 'Edit Siswa')

@section('content')
<div class="sl-card">
    <h1>Edit Siswa</h1>
    <form method="POST" action="{{ route('admin.students.update', $student) }}" class="mt-3">
        @csrf
        @method('PUT')
        <div class="mt-2">
            <label for="name">Nama</label>
            <input id="name" type="text" name="name" value="{{ old('name', $student->userBaru?->name) }}" required maxlength="255">
        </div>
        <div class="mt-2">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username', $student->userBaru?->username) }}" required maxlength="255">
        </div>
        <div class="mt-2">
            <label for="password">Password baru (kosongkan jika tidak diubah, min. 8 karakter)</label>
            <input id="password" type="password" name="password">
        </div>
        <div class="mt-2">
            <label for="password_confirmation">Konfirmasi Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation">
        </div>
        <div class="mt-2">
            <label for="nis">NIS</label>
            <input id="nis" type="text" name="nis" value="{{ old('nis', $student->nis) }}" required maxlength="50">
        </div>
        <div class="mt-2">
            <label for="class_id">Kelas</label>
            <select id="class_id" name="class_id" required>
                <option value="">Pilih Kelas</option>
                @foreach ($classes as $schoolClass)
                    <option value="{{ $schoolClass->id }}" @selected((string) old('class_id', $student->class_id) === (string) $schoolClass->id)>{{ $schoolClass->name }} — {{ $schoolClass->major?->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mt-2">
            <label for="phone">Telepon (opsional)</label>
            <input id="phone" type="text" name="phone" value="{{ old('phone', $student->phone) }}" maxlength="50">
        </div>
        <div class="mt-2">
            <label for="status">Status Akun</label>
            <select id="status" name="status" required>
                <option value="aktif" @selected(old('status', $student->userBaru?->status) === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status', $student->userBaru?->status) === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.students.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


