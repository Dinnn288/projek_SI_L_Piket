@extends('layouts.admin')

@section('title', 'Manajemen Siswa')

@section('content')
<div class="welcome-card" style="max-width: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1>Manajemen Siswa</h1>
        <a href="{{ route('admin.students.create') }}" style="background: #3498db; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">Tambah Siswa</a>
    </div>

    <table style="width: 100%; border-collapse: collapse; background: white;">
        <thead>
            <tr style="background: #f5f6fa;">
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">ID</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">NIS</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Nama</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Username</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Kelas</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Status Akun</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $student->id }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $student->nis }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $student->userBaru?->name }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $student->userBaru?->username }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $student->schoolClass?->name }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $student->userBaru?->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">
                        <a href="{{ route('admin.students.edit', $student) }}" style="margin-right: 12px;">Edit</a>
                        <form method="POST" action="{{ route('admin.students.destroy', $student) }}" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus siswa ini?');">
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
    </table>
</div>
@endsection
