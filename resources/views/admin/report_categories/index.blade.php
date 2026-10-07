@extends('layouts.admin')

@section('title', 'Manajemen Kategori & Prioritas Laporan')

@section('content')
<div class="sl-card">
    <div class="sl-card-head">
        <h1>Kategori & Prioritas Laporan</h1>
        <a href="{{ route('admin.report-categories.create') }}" class="btn">Tambah Kategori</a>
    </div>

    <x-admin-search placeholder="Cari nama kategori, prioritas, status...">
        <x-admin-filter name="priority" label="Prioritas" placeholder="Prioritas: Semua" :options="['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi']" />
        <x-admin-filter name="status" label="Status" placeholder="Status: Semua" :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']" />
    </x-admin-search>

    <x-admin-table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Kategori</th>
                <th>Prioritas</th>
                <th>Status</th>
                <th>Jumlah Laporan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td>{{ $category->id }}</td>
                    <td>{{ $category->name }}</td>
                    <td><x-priority-badge :value="$category->priority" /></td>
                    <td><x-state-badge :value="$category->status" :map="['aktif' => ['badge-aktif', 'Aktif'], 'nonaktif' => ['badge-nonaktif', 'Nonaktif']]" /></td>
                    <td>{{ $category->reports_count }}</td>
                    <td class="sl-actions"><x-admin-action-menu :editUrl="route('admin.report-categories.edit', $category)" :deleteAction="route('admin.report-categories.destroy', $category)" deleteConfirm="Apakah Anda yakin ingin menghapus kategori ini?" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"><div class="empty"><div class="empty-title">Belum ada data</div><div class="empty-hint">Tambahkan kategori pertama melalui tombol di atas.</div></div></td>
                </tr>
            @endforelse
        </tbody>
    </x-admin-table>
    <x-pagination :paginator="$categories" />
</div>
@endsection






