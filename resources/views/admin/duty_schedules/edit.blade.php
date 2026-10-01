@extends('layouts.admin')

@section('title', 'Edit Jadwal Guru Piket')

@section('content')
<div class="sl-card">
    <h1>Edit Jadwal Guru Piket</h1>
    <form method="POST" action="{{ route('admin.duty-schedules.update', $dutySchedule) }}" style="margin-top: 24px;">
        @csrf
        @method('PUT')
        <div style="margin-bottom: 16px;">
            <label for="teacher_id">Guru Piket</label><br>
            <select id="teacher_id" name="teacher_id" required style="width: 100%; margin-top: 6px; padding: 8px;">
                <option value="">Pilih Guru</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected((string) old('teacher_id', $dutySchedule->teacher_id) === (string) $teacher->id)>{{ $teacher->userBaru?->name }} — {{ $teacher->nip }}</option>
                @endforeach
            </select>
        </div>
        <div style="margin-bottom: 16px;">
            <label for="day">Hari</label><br>
            <select id="day" name="day" required style="width: 100%; margin-top: 6px; padding: 8px;">
                <option value="">Pilih Hari</option>
                <option value="senin" @selected(old('day', $dutySchedule->day) === 'senin')>Senin</option>
                <option value="selasa" @selected(old('day', $dutySchedule->day) === 'selasa')>Selasa</option>
                <option value="rabu" @selected(old('day', $dutySchedule->day) === 'rabu')>Rabu</option>
                <option value="kamis" @selected(old('day', $dutySchedule->day) === 'kamis')>Kamis</option>
                <option value="jumat" @selected(old('day', $dutySchedule->day) === 'jumat')>Jumat</option>
                <option value="sabtu" @selected(old('day', $dutySchedule->day) === 'sabtu')>Sabtu</option>
            </select>
        </div>
        <button type="submit" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Simpan</button>
        <a href="{{ route('admin.duty-schedules.index') }}" style="margin-left: 12px;">Batal</a>
    </form>
</div>
@endsection

