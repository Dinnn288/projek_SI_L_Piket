@extends('layouts.admin')

@section('title', 'Tambah Siswa')

@section('content')
<div class="welcome-card">
    <h1>Tambah Siswa</h1>
    <form method="POST" action="{{ route('admin.students.store') }}" style="margin-top: 24px;">
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
            <label for="nis">NIS</label><br>
            <input id="nis" type="text" name="nis" value="{{ old('nis') }}" required maxlength="50" style="width: 100%; margin-top: 6px; padding: 8px;">
        </div>
        <div style="margin-bottom: 16px;">
            <label for="class_id">Kelas</label><br>
            <select id="class_id" name="class_id" required style="width: 100%; margin-top: 6px; padding: 8px;">
                <option value="">Pilih Kelas</option>
                @foreach ($classes as $schoolClass)
                    <option value="{{ $schoolClass->id }}" @selected((string) old('class_id') === (string) $schoolClass->id)>{{ $schoolClass->name }} — {{ $schoolClass->major?->name }}</option>
                @endforeach
            </select>
        </div>
        <div style="margin-bottom: 16px;">
            <label for="phone">Telepon (opsional)</label><br>
            <input id="phone" type="text" name="phone" value="{{ old('phone') }}" maxlength="50" style="width: 100%; margin-top: 6px; padding: 8px;">
        </div>
        <div style="margin-bottom: 16px;">
            <label for="status">Status Akun</label><br>
            <select id="status" name="status" required style="width: 100%; margin-top: 6px; padding: 8px;">
                <option value="aktif" @selected(old('status', 'aktif') === 'aktif')>Aktif</option>
                <option value="nonaktif" @selected(old('status') === 'nonaktif')>Nonaktif</option>
            </select>
        </div>
        <button type="submit" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Simpan</button>
        <a href="{{ route('admin.students.index') }}" style="margin-left: 12px;">Batal</a>
    </form>
</div>
@endsection
