@extends('layouts.admin')

@section('title', 'Pengaturan Jam Operasional')

@section('content')
<div class="sl-card">
    <h1>Pengaturan Jam Operasional</h1>
    <p class="muted text-sm mt-1">Jam ini digunakan sebagai batas operasional Guru Piket untuk mengambil dan menangani laporan. Waktu aktual sistem tetap Asia/Jakarta.</p>
    <form method="POST" action="{{ route('admin.settings.operational-hours.update') }}" class="mt-3">
        @csrf
        @method('PUT')
        <div class="mt-2">
            <label for="operational_start_time">Jam Mulai Operasional</label>
            <input id="operational_start_time" type="time" name="operational_start_time" value="{{ old('operational_start_time', $start) }}" required>
        </div>
        <div class="mt-2">
            <label for="operational_end_time">Jam Selesai Operasional</label>
            <input id="operational_end_time" type="time" name="operational_end_time" value="{{ old('operational_end_time', $end) }}" required>
        </div>
        <button type="submit" class="btn">Simpan Pengaturan</button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Batal</a>
    </form>
</div>
@endsection


