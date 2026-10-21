@extends('layouts.admin')

@section('title', 'Edit Jadwal Piket Kelas')

@section('content')
<div class="sl-card">
    <h1>Edit Jadwal Piket Kelas</h1>
    <form method="POST" action="{{ route('admin.class-duty-schedules.update', $schedule) }}" class="mt-3">
        @csrf
        @method('PUT')
        <div class="mt-2">
            <label for="class_id">Kelas</label>
            <select id="class_id" name="class_id" required>
                <option value="">Pilih Kelas</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected((string) old('class_id', $schedule->class_id) === (string) $class->id)>{{ $class->name }} — {{ $class->major?->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mt-2">
            <label for="day">Hari</label>
            <select id="day" name="day" required>
                <option value="">Pilih Hari</option>
                <option value="senin" @selected(old('day', $schedule->day) === 'senin')>Senin</option>
                <option value="selasa" @selected(old('day', $schedule->day) === 'selasa')>Selasa</option>
                <option value="rabu" @selected(old('day', $schedule->day) === 'rabu')>Rabu</option>
                <option value="kamis" @selected(old('day', $schedule->day) === 'kamis')>Kamis</option>
                <option value="jumat" @selected(old('day', $schedule->day) === 'jumat')>Jumat</option>
            </select>
        </div>
        <button type="submit" class="btn">Simpan</button>
        <a href="{{ route('admin.class-duty-schedules.index') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection
