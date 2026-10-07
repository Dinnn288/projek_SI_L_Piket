@extends('layouts.admin')

@section('title', 'Manajemen Jadwal Guru Piket')

@section('content')
<div class="sl-card">
<div class="sl-card-head">
        <div>
            <h1>Manajemen Jadwal Guru Piket</h1>
            <p class="muted text-sm mt-1">Jam operasional guru piket: 07.00–16.00 (aturan aplikasi, tidak disimpan per jadwal).</p>
        </div>
        <a href="{{ route('admin.duty-schedules.create') }}" class="btn">Tambah Jadwal</a>
    </div>

    <x-admin-search placeholder="Cari nama guru, NIP, hari...">
        <x-admin-filter name="day" label="Hari" placeholder="Hari: Semua" :options="['senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis', 'jumat' => 'Jumat', 'sabtu' => 'Sabtu']" />
    </x-admin-search>

    @php
        $labels = ['senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis', 'jumat' => 'Jumat', 'sabtu' => 'Sabtu'];
    @endphp
    @foreach ($days as $day)
        <h2 style="margin: 20px 0 10px;">{{ $labels[$day] }}</h2>
        @if (($grouped[$day] ?? collect())->isEmpty())
            <p style="color: #7f8c8d;">Belum ada guru piket.</p>
        @else
            <x-admin-table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Guru</th>
                        <th>NIP</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grouped[$day] as $schedule)
                        <tr>
                            <td>{{ $schedule->id }}</td>
                            <td>{{ $schedule->teacher?->userBaru?->name }}</td>
                            <td>{{ $schedule->teacher?->nip }}</td>
                            <td class="sl-actions"><x-admin-action-menu :editUrl="route('admin.duty-schedules.edit', $schedule)" :deleteAction="route('admin.duty-schedules.destroy', $schedule)" deleteConfirm="Apakah Anda yakin ingin menghapus jadwal ini?" /></td>
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




