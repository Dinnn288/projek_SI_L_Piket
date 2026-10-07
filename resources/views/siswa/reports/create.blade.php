@extends('layouts.siswa')

@section('title', 'Buat Laporan')

@section('content')
<div class="sl-card">
    <h1 style="font-size: 22px; color: #1f3d2b;">Buat Laporan</h1>
    <p class="muted text-sm mt-1">Lokasi: {{ $student->schoolClass?->name ?? '-' }} (otomatis dari data kelas, tidak dapat diubah)</p>
    <form method="POST" action="{{ route('siswa.reports.store') }}" enctype="multipart/form-data" style="margin-top: 18px;">
        @csrf
        <div style="margin-bottom: 14px;">
            <label for="title">Judul</label>
            <input id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="200">
        </div>
        <div style="margin-bottom: 14px;">
            <label for="category_id">Kategori</label>
            <select id="category_id" name="category_id" required>
                <option value="">Pilih Kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }} ({{ $category->priority }})</option>
                @endforeach
            </select>
        </div>
        <div style="margin-bottom: 14px;">
            <label for="location">Lokasi detail</label>
            <input id="location" type="text" name="location" value="{{ old('location', $student->schoolClass?->name) }}" required maxlength="200" placeholder="Contoh: {{ $student->schoolClass?->name }} - depan papan tulis">
        </div>
        <div style="margin-bottom: 14px;">
            <label for="description">Deskripsi / Isi Laporan</label>
            <textarea id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
        </div>
        <div style="margin-bottom: 14px;">
            <label for="photos">Foto bukti (wajib, 1-5 foto, maks 8 MB per foto: jpg/jpeg/png/webp)</label>
            <input id="photos" type="file" name="photos[]" accept="image/*" multiple required style="margin-top: 6px;" data-sl-photos>
            <p id="photos-count" class="muted text-sm mt-1" aria-live="polite">0 / 5 foto</p>
            <p id="photos-error" class="field-error" style="display:none;"></p>
            <div id="photos-preview" class="photo-grid" aria-label="Pratinjau foto"></div>
            <p class="muted text-sm mt-1">Catatan server: batas upload PHP saat ini {{ ini_get('upload_max_filesize') }} per file dan {{ ini_get('post_max_size') }} per request. Jika foto ditolak diam-diam oleh browser/server, kecilkan ukuran foto atau hubungi admin.</p>
        </div>
        <script>
        (function () {
            var input = document.querySelector('[data-sl-photos]');
            var count = document.getElementById('photos-count');
            var err = document.getElementById('photos-error');
            var preview = document.getElementById('photos-preview');
            if (!input) return;
            input.addEventListener('change', function () {
                if (err) { err.style.display = 'none'; err.textContent = ''; }
                if (preview) preview.innerHTML = '';
                var files = Array.prototype.slice.call(input.files || []);
                // UX saja: backend tetap validator utama (maks 5, image, 8 MB/file).
                if (files.length > 5) {
                    if (err) { err.textContent = 'Maksimal 5 foto. Anda memilih ' + files.length + ' foto — hanya 5 pertama yang dipakai.'; err.style.display = 'block'; }
                    var dt = new DataTransfer();
                    files.slice(0, 5).forEach(function (f) { dt.items.add(f); });
                    input.files = dt.files;
                    files = files.slice(0, 5);
                }
                if (count) count.textContent = files.length + ' / 5 foto';
                files.forEach(function (f) {
                    if (!f.type.startsWith('image/')) return;
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        var wrap = document.createElement('div');
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = f.name;
                        img.loading = 'lazy';
                        var cap = document.createElement('div');
                        cap.className = 'caption';
                        cap.textContent = f.name;
                        wrap.appendChild(img);
                        wrap.appendChild(cap);
                        preview.appendChild(wrap);
                    };
                    reader.readAsDataURL(f);
                });
            });
        })();
        </script>
        <button type="submit" class="btn">Kirim Laporan</button>
        <a href="{{ route('siswa.reports.index') }}" class="btn btn-secondary" style="margin-left: 10px;">Batal</a>
    </form>
</div>
@endsection


