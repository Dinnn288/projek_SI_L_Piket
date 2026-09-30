@extends('layouts.admin')

@section('title', 'Manajemen Kelas')

@section('content')
<div class="welcome-card" style="max-width: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1>Manajemen Kelas</h1>
        <a href="{{ route('admin.classes.create') }}" style="background: #3498db; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">Tambah Kelas</a>
    </div>

    <table style="width: 100%; border-collapse: collapse; background: white;">
        <thead>
            <tr style="background: #f5f6fa;">
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">No</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Nama Kelas</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Jurusan</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Jumlah Siswa</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Tanggal Dibuat</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($classes as $schoolClass)
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $loop->iteration }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $schoolClass->name }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $schoolClass->major?->name }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $schoolClass->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $schoolClass->students_count }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $schoolClass->created_at?->format('d M Y') }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">
                        <a href="{{ route('admin.classes.edit', $schoolClass) }}" style="margin-right: 12px;">Edit</a>
                        <form method="POST" action="{{ route('admin.classes.destroy', $schoolClass) }}" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kelas ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background: none; border: none; color: #e74c3c; cursor: pointer; font-size: 14px;">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding: 24px; text-align: center; color: #7f8c8d;">Belum ada kelas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
