@extends('layouts.admin')

@section('title', 'Manajemen Users')

@section('content')
<div class="sl-card">
    <div class="sl-card-head">
        <div>
            <h1>Manajemen Users</h1>
            <p class="muted text-sm mt-1">Kelola akun seluruh role SI-LPiket (sumber: users_baru).</p>
        </div>
        <a href="{{ route('admin.users.create') }}" class="btn">+ Tambah User</a>
    </div>

    <x-admin-search placeholder="Cari nama, username, role, status...">
        <x-admin-filter name="role" label="Role" placeholder="Role: Semua" :options="['admin' => 'Admin', 'siswa' => 'Siswa', 'guru' => 'Guru', 'kepala_sekolah' => 'Kepala Sekolah']" />
        <x-admin-filter name="status" label="Status" placeholder="Status: Semua" :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']" />
    </x-admin-search>

    <x-admin-table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td class="sl-cell-wrap">{{ $user->name }}</td>
                    <td class="sl-cell-wrap">{{ $user->username }}</td>
                    <td>
                        <x-state-badge :value="$user->role" :map="['admin' => ['badge-proses', 'Admin'], 'siswa' => ['badge-priority-sedang', 'Siswa'], 'guru' => ['badge-priority-tinggi', 'Guru'], 'kepala_sekolah' => ['badge-forward', 'Kepala Sekolah']]" />
                    </td>
                    <td>
                        <x-state-badge :value="$user->status" :map="['aktif' => ['badge-aktif', 'Aktif'], 'nonaktif' => ['badge-nonaktif', 'Nonaktif']]" />
                    </td>
                    <td>{{ $user->created_at?->format('d M Y') }}</td>
                    <td class="sl-actions">
                        @if ($user->role === 'admin')
                            <x-admin-action-menu :editUrl="route('admin.users.edit', $user)" />
                        @else
                            <x-admin-action-menu :editUrl="route('admin.users.edit', $user)" :deleteAction="route('admin.users.destroy', $user)" :deleteConfirm="$user->role === 'guru' ? ('Hapus akun guru ' . $user->name . ' (' . $user->username . ', ' . $user->status . ')? Akun tidak dapat digunakan untuk login setelah dihapus. Semua jadwal piket milik guru ini ikut dihapus; jadwal guru lain tidak terpengaruh. Laporan dan histori penanganan tidak ikut dihapus.') : ('Hapus akun ' . $user->name . ' (' . $user->username . ', ' . $user->role . ', ' . $user->status . ')? Akun tidak dapat digunakan untuk login setelah dihapus. Laporan dan histori penanganan tetap dipertahankan sesuai aturan penyimpanan.')" />
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7"><div class="empty"><div class="empty-title">Belum ada data</div><div class="empty-hint">Tambahkan user pertama melalui tombol di atas.</div></div></td>
                </tr>
            @endforelse
        </tbody>
    </x-admin-table>
    <x-pagination :paginator="$users" />
</div>
@endsection

