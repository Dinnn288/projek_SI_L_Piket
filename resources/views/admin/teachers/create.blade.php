@extends('layouts.admin')

@section('title', 'Tambah Guru')

@section('content')
<div class="sl-card">
    <h1>Tambah Guru</h1>
    <form method="POST" action="{{ route('admin.teachers.store') }}" class="mt-3">
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
            <label for="nip">NIP</label>
            <input id="nip" type="text" name="nip" value="{{ old('nip') }}" required maxlength="50">
        </div>
        <div class="mt-2">
            <label for="phone">Telepon (opsional)</label>
            <input id="phone" type="text" name="phone" value="{{ old('phone') }}" maxlength="50">
        </div>
        <div class="mt-2">
            <label for="status">Status Akun</label>
            <select id="status" name="status" required>
                <option value="aktif" @selected(old('status', 'aktif') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status') === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.teachers.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


