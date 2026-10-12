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
            <label for="role">Role</label>
            <select id="role" name="role" required data-sl-user-role>
                <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
                <option value="siswa" @selected(old('role', $user->role) === 'siswa')>Siswa</option>
                <option value="guru" @selected(old('role', $user->role) === 'guru')>Guru</option>
                <option value="kepala_sekolah" @selected(old('role', $user->role) === 'kepala_sekolah')>Kepala Sekolah</option>
            </select>
        </div>
        <div class="mt-2" data-sl-user-nis-wrap>
            <label for="nis">NIS</label>
            <input id="nis" type="text" name="nis" value="{{ old('nis', $user->student?->nis) }}" maxlength="50">
        </div>
        <div class="mt-2" data-sl-user-nip-wrap>
            <label for="nip">NIP</label>
            <input id="nip" type="text" name="nip" value="{{ old('nip', $user->teacher?->nip) }}" maxlength="50">
        </div>
        <div class="mt-2" data-sl-user-class-wrap>
            <label for="class_id">Kelas (khusus siswa)</label>
            <select id="class_id" name="class_id">
                <option value="">Pilih Kelas</option>
                @foreach ($classes as $schoolClass)
                    <option value="{{ $schoolClass->id }}" @selected((string) old('class_id', $user->student?->class_id) === (string) $schoolClass->id)>{{ $schoolClass->name }} — {{ $schoolClass->major?->name }}</option>
                @endforeach
            </select>
        </div>
        <script>
        (function () {
            var role = document.querySelector('[data-sl-user-role]');
            var nisWrap = document.querySelector('[data-sl-user-nis-wrap]');
            var nipWrap = document.querySelector('[data-sl-user-nip-wrap]');
            var classWrap = document.querySelector('[data-sl-user-class-wrap]');
            var nis = document.getElementById('nis');
            var nip = document.getElementById('nip');
            var classId = document.getElementById('class_id');
            if (!role || !nisWrap || !nipWrap || !classWrap) return;
            function sync() {
                var v = role.value;
                var isSiswa = v === 'siswa';
                var isGuru = v === 'guru';
                nisWrap.style.display = isSiswa ? '' : 'none';
                nipWrap.style.display = isGuru ? '' : 'none';
                classWrap.style.display = isSiswa ? '' : 'none';
                // Nilai field yang disembunyikan dikosongkan agar NIS tidak
                // tertukar ke NIP (dan sebaliknya) saat role diganti.
                if (!isSiswa && nis) nis.value = '';
                if (!isGuru && nip) nip.value = '';
                if (!isSiswa && classId) classId.value = '';
                if (nis) nis.required = isSiswa;
                if (nip) nip.required = isGuru;
                if (classId) classId.required = isSiswa;
            }
            role.addEventListener('change', sync);
            sync();
        })();
        </script>
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


