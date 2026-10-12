@extends('layouts.admin')

@section('title', 'Edit Guru')

@section('content')
<div class="sl-card">
    <h1>Edit Guru</h1>
    <form method="POST" action="{{ route('admin.teachers.update', $teacher) }}" class="mt-3">
        @csrf
        @method('PUT')
        <div class="mt-2">
            <label for="name">Nama</label>
            <input id="name" type="text" name="name" value="{{ old('name', $teacher->userBaru?->name) }}" required maxlength="255">
        </div>
        <div class="mt-2">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username', $teacher->userBaru?->username) }}" required maxlength="255">
        </div>
        <div class="mt-2">
            <label for="password">Password baru (kosongkan jika tidak diubah, min. 8 karakter)</label>
            <div class="sl-pw-field" data-sl-pw-field>
                <input id="password" type="password" name="password">
                <button type="button" class="sl-pw-toggle" data-sl-pw-toggle aria-label="Tampilkan password" aria-pressed="false" title="Tampilkan password"></button>
            </div>
        </div>
        <div class="mt-2">
            <label for="password_confirmation">Konfirmasi Password</label>
            <div class="sl-pw-field" data-sl-pw-field>
                <input id="password_confirmation" type="password" name="password_confirmation">
                <button type="button" class="sl-pw-toggle" data-sl-pw-toggle aria-label="Tampilkan password" aria-pressed="false" title="Tampilkan password"></button>
            </div>
        </div>
        <div class="mt-2">
            <label for="nip">NIP</label>
            <input id="nip" type="text" name="nip" value="{{ old('nip', $teacher->nip) }}" required maxlength="50">
        </div>
        <div class="mt-2">
            <label for="phone">Telepon (opsional)</label>
            <input id="phone" type="text" name="phone" value="{{ old('phone', $teacher->phone) }}" maxlength="50">
        </div>
        <div class="mt-2">
            <label for="status">Status Akun</label>
            <select id="status" name="status" required>
                <option value="aktif" @selected(old('status', $teacher->userBaru?->status) === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status', $teacher->userBaru?->status) === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.teachers.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


