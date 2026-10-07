@extends('layouts.admin')

@section('title', 'Manajemen Guru')

@section('content')
<div class="sl-card">
    <div class="sl-card-head">
        <h1>Manajemen Guru</h1>
        <a href="{{ route('admin.teachers.create') }}" class="btn">Tambah Guru</a>
    </div>

    <x-admin-search placeholder="Cari NIP, nama, username...">
        <x-admin-filter name="status" label="Status" placeholder="Status: Semua" :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']" />
    </x-admin-search>

    <x-admin-table>
        <thead>
            <tr>
                <th>ID</th>
                <th>NIP</th>
                <th>Nama</th>
                <th>Username</th>
                <th>Status Akun</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($teachers as $teacher)
                <tr>
                    <td>{{ $teacher->id }}</td>
                    <td>{{ $teacher->nip }}</td>
                    <td>{{ $teacher->userBaru?->name }}</td>
                    <td>{{ $teacher->userBaru?->username }}</td>
                    <td><x-state-badge :value="$teacher->userBaru?->status" :map="['aktif' => ['badge-aktif', 'Aktif'], 'nonaktif' => ['badge-nonaktif', 'Nonaktif']]" /></td>
                    <td class="sl-actions"><x-admin-action-menu :editUrl="route('admin.teachers.edit', $teacher)" :deleteAction="route('admin.teachers.destroy', $teacher)" deleteConfirm="Apakah Anda yakin ingin menghapus guru ini?" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"><div class="empty"><div class="empty-title">Belum ada data</div><div class="empty-hint">Tambahkan guru pertama melalui tombol di atas.</div></div></td>
                </tr>
            @endforelse
        </tbody>
    </x-admin-table>
    <x-pagination :paginator="$teachers" />
</div>
@endsection






