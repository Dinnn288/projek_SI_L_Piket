@extends('layouts.admin')

@section('title', 'Manajemen Jadwal Piket Kelas')

@section('content')
<div class="sl-card">
    <div class="sl-card-head">
        <div>
            <h1>Manajemen Jadwal Piket Kelas</h1>
            <p class="muted text-sm mt-1">Seluruh siswa satu kelas bertugas bersama. Satu hari dapat memiliki lebih dari satu kelas piket. Kelas yang sama tidak boleh duplikat pada hari yang sama.</p>
        </div>
        <a href="{{ route('admin.class-duty-schedules.create') }}" class="btn">Tambah Jadwal</a>
    </div>

    <x-admin-search placeholder="Cari nama kelas, jurusan, hari...">
        <x-admin-filter name="day" label="Hari" placeholder="Hari: Semua" :options="['senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis', 'jumat' => 'Jumat']" />
    </x-admin-search>

    @php
        $labels = ['senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis', 'jumat' => 'Jumat'];
    @endphp
    @foreach ($days as $day)
        <h2 style="margin: 20px 0 10px;">{{ $labels[$day] }}</h2>
        @if (($grouped[$day] ?? collect())->isEmpty())
            <p style="color: #7f8c8d;">Belum ada kelas piket.</p>
        @else
            <x-admin-table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kelas</th>
                        <th>Jurusan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grouped[$day] as $schedule)
                        <tr>
                            <td>{{ $schedule->id }}</td>
                            <td>{{ $schedule->schoolClass?->name }}</td>
                            <td>{{ $schedule->schoolClass?->major?->name }}</td>
                            <td class="sl-actions"><x-admin-action-menu :editUrl="route('admin.class-duty-schedules.edit', $schedule)" :deleteAction="route('admin.class-duty-schedules.destroy', $schedule)" deleteConfirm="Hapus jadwal ini? Laporan piket yang sudah tersimpan tidak ikut dihapus." /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-admin-table>
        @endif
    @endforeach

    @if ($schedules->isEmpty())
        <p style="padding: 24px; text-align: center; color: #7f8c8d;">Belum ada jadwal.</p>
    @endif
</div>
@endsection
