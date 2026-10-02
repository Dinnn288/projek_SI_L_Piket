@extends('layouts.admin')

@section('title', 'Tambah User')

@section('content')
<div class="sl-card">
    <h1>Tambah User</h1>
    <form method="POST" action="{{ route('admin.users.store') }}" class="mt-3">
        @csrf
        <div class="mt-2">
            <label for="name">Nama</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="255">
        </div>
        <div class="mt-2">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required maxlength="255">
        </div>
        <div class="mt-2">
            <label for="password">Password (min. 8 karakter)</label>
            <input id="password" type="password" name="password" required>
        </div>
        <div class="mt-2">
            <label for="password_confirmation">Konfirmasi Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>
        </div>
        <div class="mt-2">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                <option value="siswa" @selected(old('role', 'siswa') === 'siswa')>Siswa</option>
                <option value="guru" @selected(old('role') === 'guru')>Guru</option>
                <option value="kepala_sekolah" @selected(old('role') === 'kepala_sekolah')>Kepala Sekolah</option>
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
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


