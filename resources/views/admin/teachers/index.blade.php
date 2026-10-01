@extends('layouts.admin')

@section('title', 'Manajemen Guru')

@section('content')
<div class="sl-card">
    <div class="sl-card-head">
        <h1>Manajemen Guru</h1>
        <a href="{{ route('admin.teachers.create') }}" style="background: #3498db; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">Tambah Guru</a>
    </div>

    <x-admin-table>
        <thead>
            <tr style="background: #f5f6fa;">
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">ID</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">NIP</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Nama</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Username</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Status Akun</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($teachers as $teacher)
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $teacher->id }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $teacher->nip }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $teacher->userBaru?->name }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $teacher->userBaru?->username }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $teacher->userBaru?->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}</td>
                    <td class="sl-actions"><a href="{{ route('admin.teachers.edit', $teacher) }}" style="margin-right: 12px;">Edit</a>
                        <form method="POST" action="{{ route('admin.teachers.destroy', $teacher) }}" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus guru ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 14px;">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="padding: 24px; text-align: center; color: #7f8c8d;">Belum ada data</td>
                </tr>
            @endforelse
        </tbody>
    </x-admin-table>
    <x-pagination :paginator="$teachers" />
</div>
@endsection




