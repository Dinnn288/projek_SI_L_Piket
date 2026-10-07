@extends('layouts.admin')

@section('title', 'Manajemen Kelas')

@section('content')
<div class="sl-card">
    <div class="sl-card-head">
        <h1>Manajemen Kelas</h1>
        <a href="{{ route('admin.classes.create') }}" class="btn">Tambah Kelas</a>
    </div>

    <x-admin-search placeholder="Cari nama kelas, jurusan, status...">
        <x-admin-filter name="major_id" label="Jurusan" placeholder="Jurusan: Semua" :options="$majors->pluck('name', 'id')->all()" />
        <x-admin-filter name="status" label="Status" placeholder="Status: Semua" :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']" />
    </x-admin-search>

    <x-admin-table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Kelas</th>
                <th>Jurusan</th>
                <th>Status</th>
                <th>Jumlah Siswa</th>
                <th>Tanggal Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($classes as $schoolClass)
                <tr>
                    <td>{{ ($classes->firstItem() ?? 0) + $loop->index }}</td>
                    <td>{{ $schoolClass->name }}</td>
                    <td>{{ $schoolClass->major?->name }}</td>
                    <td><x-state-badge :value="$schoolClass->status" :map="['aktif' => ['badge-aktif', 'Aktif'], 'nonaktif' => ['badge-nonaktif', 'Nonaktif']]" /></td>
                    <td>{{ $schoolClass->students_count }}</td>
                    <td>{{ $schoolClass->created_at?->format('d M Y') }}</td>
                    <td class="sl-actions"><x-admin-action-menu :editUrl="route('admin.classes.edit', $schoolClass)" :deleteAction="route('admin.classes.destroy', $schoolClass)" deleteConfirm="Apakah Anda yakin ingin menghapus kelas ini?" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7"><div class="empty">Belum ada kelas.</div></td>
                </tr>
            @endforelse
        </tbody>
    </x-admin-table>
    <x-pagination :paginator="$classes" />
</div>
@endsection







