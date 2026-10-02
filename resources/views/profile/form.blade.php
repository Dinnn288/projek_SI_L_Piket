<div class="sl-card">
    <h2>Informasi Akun</h2>
    <table class="kv-table mt-2">
        <tbody>
            <tr><td>Nama</td><td>{{ $profileUser->name }}</td></tr>
            <tr><td>Username</td><td>{{ $profileUser->username }}</td></tr>
            <tr><td>Role</td><td style="text-transform: capitalize;">{{ str_replace('_', ' ', $profileUser->role) }}</td></tr>
            <tr><td>Status</td><td><x-state-badge :value="$profileUser->status" :map="['aktif' => ['badge-aktif', 'Aktif'], 'nonaktif' => ['badge-nonaktif', 'Nonaktif']]" /></td></tr>
        </tbody>
    </table>
</div>

<div class="sl-card">
    <h2>Ubah Password</h2>
    <p class="muted text-sm mt-1">Gunakan password minimal 8 karakter yang berbeda dari password saat ini.</p>
    <form method="POST" action="{{ $updateRoute }}" class="mt-3">
        @csrf
        @method('PUT')
        <div class="mt-2">
            <label for="current_password">Password Saat Ini <span class="required-mark">*</span></label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="mt-2">
            <label for="password">Password Baru (min. 8 karakter) <span class="required-mark">*</span></label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
        </div>
        <div class="mt-2">
            <label for="password_confirmation">Konfirmasi Password Baru <span class="required-mark">*</span></label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Ubah Password</button>
        </div>
    </form>
</div>
