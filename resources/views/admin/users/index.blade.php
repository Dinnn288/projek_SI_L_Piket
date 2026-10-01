@extends('layouts.admin')

@section('title', 'Manajemen Users')

@section('content')
<div class="sl-card">
    <div class="sl-card-head">
        <h1>Manajemen Users</h1>
        <a href="{{ route('admin.users.create') }}" style="background: #3498db; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">Tambah User</a>
    </div>

    <x-admin-table>
        <thead>
            <tr style="background: #f5f6fa;">
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">ID</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Nama</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Username</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Role</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Dibuat</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $user->id }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $user->name }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $user->username }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">
                        @switch($user->role)
                            @case('admin') Admin @break
                            @case('siswa') Siswa @break
                            @case('guru') Guru @break
                            @case('kepala_sekolah') Kepala Sekolah @break
                            @default {{ $user->role }}
                        @endswitch
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $user->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $user->created_at?->format('d M Y') }}</td>
                    <td class="sl-actions"><a href="{{ route('admin.users.edit', $user) }}" style="margin-right: 12px;">Edit</a>
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus user ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 14px;">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding: 24px; text-align: center; color: #7f8c8d;">Belum ada data</td>
                </tr>
            @endforelse
        </tbody>
    </x-admin-table>
    <x-pagination :paginator="$users" />
</div>
@endsection



