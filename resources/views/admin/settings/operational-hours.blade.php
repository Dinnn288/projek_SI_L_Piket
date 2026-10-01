@extends('layouts.admin')

@section('title', 'Pengaturan Jam Operasional')

@section('content')
<div class="sl-card">
    <h1>Pengaturan Jam Operasional</h1>
    <p style="color: #5d6d7e; margin-top: 6px;">Jam ini digunakan sebagai batas operasional Guru Piket untuk mengambil dan menangani laporan. Waktu aktual sistem tetap Asia/Jakarta.</p>
    <form method="POST" action="{{ route('admin.settings.operational-hours.update') }}" style="margin-top: 24px;">
        @csrf
        @method('PUT')
        <div style="margin-bottom: 16px;">
            <label for="operational_start_time">Jam Mulai Operasional</label><br>
            <input id="operational_start_time" type="time" name="operational_start_time" value="{{ old('operational_start_time', $start) }}" required style="margin-top: 6px; padding: 8px;">
        </div>
        <div style="margin-bottom: 16px;">
            <label for="operational_end_time">Jam Selesai Operasional</label><br>
            <input id="operational_end_time" type="time" name="operational_end_time" value="{{ old('operational_end_time', $end) }}" required style="margin-top: 6px; padding: 8px;">
        </div>
        <button type="submit" style="background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">Simpan Pengaturan</button>
        <a href="{{ route('admin.dashboard') }}" style="margin-left: 12px;">Batal</a>
    </form>
</div>
@endsection

