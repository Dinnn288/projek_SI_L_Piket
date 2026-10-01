@extends('layouts.admin')

@section('title', 'Manajemen Kategori & Prioritas Laporan')

@section('content')
<div class="sl-card">
    <div class="sl-card-head">
        <h1>Kategori & Prioritas Laporan</h1>
        <a href="{{ route('admin.report-categories.create') }}" style="background: #3498db; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">Tambah Kategori</a>
    </div>

    <x-admin-table>
        <thead>
            <tr style="background: #f5f6fa;">
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">ID</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Kategori</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Prioritas</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Jumlah Laporan</th>
                <th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e8ed;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $category->id }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $category->name }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">
                        @switch($category->priority)
                            @case('tinggi') Tinggi @break
                            @case('sedang') Sedang @break
                            @case('rendah') Rendah @break
                            @default {{ $category->priority }}
                        @endswitch
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $category->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}</td>
                    <td style="padding: 12px; border-bottom: 1px solid #e1e8ed;">{{ $category->reports_count }}</td>
                    <td class="sl-actions"><a href="{{ route('admin.report-categories.edit', $category) }}" style="margin-right: 12px;">Edit</a>
                        <form method="POST" action="{{ route('admin.report-categories.destroy', $category) }}" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori ini?');">
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
    <x-pagination :paginator="$categories" />
</div>
@endsection




