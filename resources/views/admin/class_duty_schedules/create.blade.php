@extends('layouts.admin')

@section('title', 'Tambah Jadwal Piket Kelas')

@section('content')
<div class="sl-card">
    <h1>Tambah Jadwal Piket Kelas</h1>
    <p class="muted text-sm mt-1">Pilih kelas aktif dan hari. Satu kelas satu hari hanya boleh satu jadwal.</p>
    <form method="POST" action="{{ route('admin.class-duty-schedules.store') }}" class="mt-3">
        @csrf
        <div class="mt-2">
            <label for="class_id">Kelas</label>
            <select id="class_id" name="class_id" required>
                <option value="">Pilih Kelas</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((string) old('class_id') === (string) $class->id)>{{ $class->name }} — {{ $class->major?->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mt-2">
            <label for="day">Hari</label>
            <select id="day" name="day" required>
                <option value="">Pilih Hari</option>
                <option value="senin" @selected(old('day') === 'senin')>Senin</option>
                <option value="selasa" @selected(old('day') === 'selasa')>Selasa</option>
                <option value="rabu" @selected(old('day') === 'rabu')>Rabu</option>
                <option value="kamis" @selected(old('day') === 'kamis')>Kamis</option>
                <option value="jumat" @selected(old('day') === 'jumat')>Jumat</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.class-duty-schedules.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection
