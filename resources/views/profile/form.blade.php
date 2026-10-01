<div class="sl-card">
    <h2>Informasi Akun</h2>
    <table style="margin-top: 12px;">
        <tbody>
            <tr><td style="width: 180px; color: var(--muted);">Nama</td><td>{{ $profileUser->name }}</td></tr>
            <tr><td style="color: var(--muted);">Username</td><td>{{ $profileUser->username }}</td></tr>
            <tr><td style="color: var(--muted);">Role</td><td style="text-transform: capitalize;">{{ str_replace('_', ' ', $profileUser->role) }}</td></tr>
            <tr><td style="color: var(--muted);">Status</td><td>{{ $profileUser->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}</td></tr>
        </tbody>
    </table>
</div>

<div class="sl-card">
    <h2>Ubah Password</h2>
    <p style="color: var(--muted); margin-top: 4px;">Gunakan password minimal 8 karakter yang berbeda dari password saat ini.</p>
    <form method="POST" action="{{ $updateRoute }}" style="margin-top: 16px;">
        @csrf
        @method('PUT')
        <div style="margin-bottom: 14px;">
            <label for="current_password">Password Saat Ini</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
        </div>
        <div style="margin-bottom: 14px;">
            <label for="password">Password Baru (min. 8 karakter)</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
        </div>
        <div style="margin-bottom: 18px;">
            <label for="password_confirmation">Konfirmasi Password Baru</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn">Ubah Password</button>
    </form>
</div>
