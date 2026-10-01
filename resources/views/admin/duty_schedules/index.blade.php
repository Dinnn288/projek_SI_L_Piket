@extends('layouts.admin')

@section('title', 'Manajemen Jadwal Guru Piket')

@section('content')
<div class="sl-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1>Manajemen Jadwal Guru Piket</h1>
        <a href="{{ route('admin.duty-schedules.create') }}" style="background: #3498db; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">Tambah Jadwal</a>
    </div>
    <p style="color: #5d6d7e; margin-bottom: 16px;">Jam operasional guru piket: 07.00–16.00 (aturan aplikasi, tidak disimpan per jadwal).</p>

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
                    <tr style="background: #f5f6fa;">
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">ID</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Guru</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">NIP</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grouped[$day] as $schedule)
                        <tr>
                            <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $schedule->id }}</td>
                            <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $schedule->teacher?->userBaru?->name }}</td>
                            <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $schedule->teacher?->nip }}</td>
                            <td class="sl-actions"><a href="{{ route('admin.duty-schedules.edit', $schedule) }}" style="margin-right: 12px;">Edit</a>
                                <form method="POST" action="{{ route('admin.duty-schedules.destroy', $schedule) }}" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 14px;">Hapus</button>
                                </form>
                            </td>
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


