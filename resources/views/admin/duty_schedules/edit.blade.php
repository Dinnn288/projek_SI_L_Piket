@extends('layouts.admin')

@section('title', 'Edit Jadwal Guru Piket')

@section('content')
<div class="sl-card">
    <h1>Edit Jadwal Guru Piket</h1>
    <form method="POST" action="{{ route('admin.duty-schedules.update', $dutySchedule) }}" class="mt-3">
        @csrf
        @method('PUT')
        <div class="mt-2">
            <label for="teacher_id">Guru Piket</label>
            <select id="teacher_id" name="teacher_id" required>
                <option value="">Pilih Guru</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected((string) old('teacher_id', $dutySchedule->teacher_id) === (string) $teacher->id)>{{ $teacher->userBaru?->name }} — {{ $teacher->nip }}</option>
                @endforeach
            </select>
        </div>
        <div class="mt-2">
            <label for="day">Hari</label>
            <select id="day" name="day" required>
                <option value="">Pilih Hari</option>
                <option value="senin" @selected(old('day', $dutySchedule->day) === 'senin')>Senin</option>
                <option value="selasa" @selected(old('day', $dutySchedule->day) === 'selasa')>Selasa</option>
                <option value="rabu" @selected(old('day', $dutySchedule->day) === 'rabu')>Rabu</option>
                <option value="kamis" @selected(old('day', $dutySchedule->day) === 'kamis')>Kamis</option>
                <option value="jumat" @selected(old('day', $dutySchedule->day) === 'jumat')>Jumat</option>
                <option value="sabtu" @selected(old('day', $dutySchedule->day) === 'sabtu')>Sabtu</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.duty-schedules.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


