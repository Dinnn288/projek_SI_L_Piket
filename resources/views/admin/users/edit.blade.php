@extends('layouts.admin')

@section('title', 'Edit User')

@section('content')
<div class="sl-card">
    <h1>Edit User</h1>
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-3">
        @csrf
        @method('PUT')
        <div class="mt-2">
            <label for="name">Nama</label>
            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
        </div>
        <div class="mt-2">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username', $user->username) }}" required maxlength="255">
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
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                <option value="siswa" @selected(old('role', $user->role) === 'siswa')>Siswa</option>
                <option value="guru" @selected(old('role', $user->role) === 'guru')>Guru</option>
                <option value="kepala_sekolah" @selected(old('role', $user->role) === 'kepala_sekolah')>Kepala Sekolah</option>
            </select>
        </div>
        <div class="mt-2">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <option value="aktif" @selected(old('status', $user->status) === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status', $user->status) === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


