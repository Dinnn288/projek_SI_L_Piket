@extends('layouts.admin')

@section('title', 'Manajemen Siswa')

@section('content')
<div class="sl-card">
    <div class="sl-card-head">
        <h1>Manajemen Siswa</h1>
        <a href="{{ route('admin.students.create') }}" class="btn">Tambah Siswa</a>
    </div>

    <x-admin-search placeholder="Cari NIS, nama, username, kelas...">
        <x-admin-filter name="class_id" label="Kelas" placeholder="Kelas: Semua" :options="$classes->pluck('name', 'id')->all()" />
        <x-admin-filter name="status" label="Status" placeholder="Status: Semua" :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']" />
    </x-admin-search>

    <x-admin-table>
        <thead>
            <tr>
                <th>ID</th>
                <th>NIS</th>
                <th>Nama</th>
                <th>Username</th>
                <th>Kelas</th>
                <th>Status Akun</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $student)
                <tr>
                    <td>{{ $student->id }}</td>
                    <td>{{ $student->nis }}</td>
                    <td>{{ $student->userBaru?->name }}</td>
                    <td>{{ $student->userBaru?->username }}</td>
                    <td>{{ $student->schoolClass?->name }}</td>
                    <td><x-state-badge :value="$student->userBaru?->status" :map="['aktif' => ['badge-aktif', 'Aktif'], 'nonaktif' => ['badge-nonaktif', 'Nonaktif']]" /></td>
                    <td class="sl-actions"><x-admin-action-menu :editUrl="route('admin.students.edit', $student)" :deleteAction="route('admin.students.destroy', $student)" deleteConfirm="Apakah Anda yakin ingin menghapus siswa ini?" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7"><div class="empty"><div class="empty-title">Belum ada data</div><div class="empty-hint">Tambahkan siswa pertama melalui tombol di atas.</div></div></td>
                </tr>
            @endforelse
        </tbody>
    </x-admin-table>
    <x-pagination :paginator="$students" />
</div>
@endsection






