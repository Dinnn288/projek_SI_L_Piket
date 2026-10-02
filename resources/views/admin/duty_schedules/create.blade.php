@extends('layouts.admin')

@section('title', 'Tambah Jadwal Guru Piket')

@section('content')
<div class="sl-card">
    <h1>Tambah Jadwal Guru Piket</h1>
    <p class="muted text-sm mt-1">Satu hari dapat memiliki lebih dari satu guru piket. Guru yang sama tidak boleh duplikat pada hari yang sama.</p>
    <form method="POST" action="{{ route('admin.duty-schedules.store') }}" class="mt-3">
        @csrf
        <div class="mt-2">
            <label for="teacher_id">Guru Piket</label>
            <select id="teacher_id" name="teacher_id" required>
                <option value="">Pilih Guru</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected((string) old('teacher_id') === (string) $teacher->id)>{{ $teacher->userBaru?->name }} — {{ $teacher->nip }}</option>
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
                <option value="sabtu" @selected(old('day') === 'sabtu')>Sabtu</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.duty-schedules.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


