@extends('layouts.admin')

@section('title', 'Manajemen Jurusan')

@section('content')
<div class="sl-card">
    <div class="sl-card-head">
        <h1>Manajemen Jurusan</h1>
        <a href="{{ route('admin.majors.create') }}" class="btn">Tambah Jurusan</a>
    </div>

    <x-admin-search placeholder="Cari nama jurusan, status...">
        <x-admin-filter name="status" label="Status" placeholder="Status: Semua" :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']" />
    </x-admin-search>

    <x-admin-table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Jurusan</th>
                <th>Status</th>
                <th>Jumlah Kelas</th>
                <th>Tanggal Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($majors as $major)
                <tr>
                    <td>{{ ($majors->firstItem() ?? 0) + $loop->index }}</td>
                    <td>{{ $major->name }}</td>
                    <td><x-state-badge :value="$major->status" :map="['aktif' => ['badge-aktif', 'Aktif'], 'nonaktif' => ['badge-nonaktif', 'Nonaktif']]" /></td>
                    <td>{{ $major->classes_count }}</td>
                    <td>{{ $major->created_at?->format('d M Y') }}</td>
                    <td class="sl-actions"><x-admin-action-menu :editUrl="route('admin.majors.edit', $major)" :deleteAction="route('admin.majors.destroy', $major)" deleteConfirm="Apakah Anda yakin ingin menghapus jurusan ini?" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"><div class="empty">Belum ada jurusan.</div></td>
                </tr>
            @endforelse
        </tbody>
    </x-admin-table>
    <x-pagination :paginator="$majors" />
</div>
@endsection







