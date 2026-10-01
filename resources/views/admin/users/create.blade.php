@extends('layouts.admin')

@section('title', 'Tambah User')

@section('content')
<div class="sl-card">
    <h1>Tambah User</h1>
    <form method="POST" action="{{ route('admin.users.store') }}" style="margin-top: 24px;">
        @csrf
        <div style="margin-bottom: 16px;">
            <label for="name">Nama</label><br>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="255" style="width: 100%; margin-top: 6px; padding: 8px;">
        </div>
        <div style="margin-bottom: 16px;">
            <label for="username">Username</label><br>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required maxlength="255" style="width: 100%; margin-top: 6px; padding: 8px;">
        </div>
        <div style="margin-bottom: 16px;">
            <label for="password">Password (min. 8 karakter)</label><br>
            <input id="password" type="password" name="password" required style="width: 100%; margin-top: 6px; padding: 8px;">
        </div>
        <div style="margin-bottom: 16px;">
            <label for="password_confirmation">Konfirmasi Password</label><br>
            <input id="password_confirmation" type="password" name="password_confirmation" required style="width: 100%; margin-top: 6px; padding: 8px;">
        </div>
        <div style="margin-bottom: 16px;">
            <label for="role">Role</label><br>
            <select id="role" name="role" required style="width: 100%; margin-top: 6px; padding: 8px;">
                <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                <option value="siswa" @selected(old('role', 'siswa') === 'siswa')>Siswa</option>
                <option value="guru" @selected(old('role') === 'guru')>Guru</option>
                <option value="kepala_sekolah" @selected(old('role') === 'kepala_sekolah')>Kepala Sekolah</option>
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
        <a href="{{ route('admin.users.index') }}" style="margin-left: 12px;">Batal</a>
    </form>
</div>
@endsection

