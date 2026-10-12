@extends('layouts.siswa')

@section('title', 'Buat Laporan')

@section('content')
<div class="sl-card sl-report-create">
    <h1 style="font-size: 22px; color: #1f3d2b;">Buat Laporan</h1>
    <p class="muted text-sm mt-1">Lokasi: {{ $student->schoolClass?->name ?? '-' }} (otomatis dari data kelas, tidak dapat diubah)</p>
    <form method="POST" action="{{ route('siswa.reports.store') }}" enctype="multipart/form-data" class="sl-report-form" data-sl-report-form>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}" data-sl-idempotency-key>
        <div class="sl-report-field">
            <label for="title">Judul</label>
            <input id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="200">
        </div>
        <div class="sl-report-field">
            <label for="category_id">Kategori</label>
            <select id="category_id" name="category_id" required>
                <option value="">Pilih Kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }} ({{ $category->priority }})</option>
                @endforeach
            </select>
        </div>
        <div class="sl-report-field">
            <label for="location">Lokasi detail</label>
            <input id="location" type="text" name="location" value="{{ old('location', $student->schoolClass?->name) }}" required maxlength="200" placeholder="Contoh: {{ $student->schoolClass?->name }} - depan papan tulis">
        </div>
        <div class="sl-report-field">
            <label for="description">Deskripsi / Isi Laporan</label>
            <textarea id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
        </div>
        <div class="sl-report-field" data-sl-photo-upload>
            <label id="photos-label">Foto bukti (wajib, 1-5 foto, maks 8 MB per foto: jpg/jpeg/png/webp)</label>
            <div class="sl-photo-upload">
                <div class="sl-photo-actions">
                    <button type="button" class="btn" data-sl-photo-camera-btn>&#128247; Ambil Foto</button>
                    <span class="sl-photo-or" aria-hidden="true">atau</span>
                    <button type="button" class="btn btn-secondary" data-sl-photo-gallery-btn>&#128444;&#65039; Pilih dari Galeri</button>
                </div>
                <div class="sl-camera" data-sl-camera hidden>
                    <p class="sl-camera-title">Kamera SI-LPiket</p>
                    <video data-sl-camera-video playsinline muted autoplay aria-label="Pratinjau kamera"></video>
                    <canvas data-sl-camera-canvas class="sl-photo-hidden" aria-hidden="true"></canvas>
                    <div class="sl-photo-actions">
                        <button type="button" class="btn" data-sl-camera-capture>&#128247; Ambil Foto</button>
                        <button type="button" class="btn btn-secondary" data-sl-camera-close>Tutup Kamera</button>
                    </div>
                    <p class="muted text-sm mt-1" data-sl-camera-hint>Kamera hanya menyala setelah Anda menekan Ambil Foto. Izin kamera diminta browser saat itu.</p>
                </div>
                <input id="photos-gallery" type="file" accept="image/*,.jpg,.jpeg,.png,.webp" multiple class="sl-photo-hidden" tabindex="-1" aria-hidden="true" data-sl-photos-gallery>
                <input id="photos" type="file" name="photos[]" accept="image/*" multiple required class="sl-photo-hidden" tabindex="-1" aria-hidden="true" data-sl-photos>
                <p class="muted text-sm mt-1">Maksimal 5 foto &bull; Maks. 8 MB/foto &bull; JPG, JPEG, PNG, WEBP. Kamera membutuhkan HTTPS/localhost; jika kamera tidak tersedia atau ditolak, gunakan Pilih dari Galeri.</p>
                <p id="photos-count" class="muted text-sm mt-1" aria-live="polite">0 / 5 foto</p>
                <p id="photos-error" class="field-error" style="display:none;" role="alert"></p>
                <div id="photos-preview" class="photo-grid" aria-label="Pratinjau foto"></div>
            </div>
            <p class="muted text-sm mt-1">Catatan server: batas upload PHP saat ini {{ ini_get('upload_max_filesize') }} per file dan {{ ini_get('post_max_size') }} per request. Jika foto ditolak diam-diam oleh browser/server, kecilkan ukuran foto atau hubungi admin.</p>
        </div>
        <script>
        (function () {
            var root = document.querySelector('[data-sl-photo-upload]');
            if (!root) return;
            var form = root.closest('form');
            var galleryInput = root.querySelector('[data-sl-photos-gallery]');
            var realInput = root.querySelector('[data-sl-photos]');
            var cameraBtn = root.querySelector('[data-sl-photo-camera-btn]');
            var galleryBtn = root.querySelector('[data-sl-photo-gallery-btn]');
            var camPanel = root.querySelector('[data-sl-camera]');
            var camVideo = root.querySelector('[data-sl-camera-video]');
            var camCanvas = root.querySelector('[data-sl-camera-canvas]');
            var camCapture = root.querySelector('[data-sl-camera-capture]');
            var camClose = root.querySelector('[data-sl-camera-close]');
            var count = document.getElementById('photos-count');
            var err = document.getElementById('photos-error');
            var preview = document.getElementById('photos-preview');
            if (!galleryInput || !realInput || !camPanel || !camVideo) return;
            var MAX_FILES = 5;
            var MAX_SIZE = 8 * 1024 * 1024;
            var ALLOWED_TYPES = { 'image/jpeg': 1, 'image/png': 1, 'image/webp': 1 };
            var ALLOWED_EXT = { 'jpg': 1, 'jpeg': 1, 'png': 1, 'webp': 1 };
            var files = [];
            // Satu stream aktif; cegah multiple stream & pastikan kamera mati total saat ditutup.
            var cameraStream = null;
            function showError(msg) {
                if (!err) return;
                err.textContent = msg;
                err.style.display = 'block';
            }
            function clearError() {
                if (!err) return;
                err.textContent = '';
                err.style.display = 'none';
            }
            function extOf(name) {
                var parts = String(name || '').toLowerCase().split('.');
                return parts.length > 1 ? parts.pop() : '';
            }
            function validType(file) {
                if (file && ALLOWED_TYPES[file.type]) return true;
                return !!ALLOWED_EXT[extOf(file && file.name)];
            }
            function formatSize(bytes) {
                if (!bytes) return '';
                return (bytes / 1024).toFixed(1) + ' KB';
            }
            function sync() {
                var dt = new DataTransfer();
                files.forEach(function (f) { dt.items.add(f); });
                realInput.files = dt.files;
                realInput.required = files.length === 0;
                if (count) count.textContent = files.length + ' / 5 foto';
                var full = files.length >= MAX_FILES;
                if (cameraBtn) cameraBtn.disabled = full;
                if (galleryBtn) galleryBtn.disabled = full;
                render();
            }
            function render() {
                if (!preview) return;
                preview.innerHTML = '';
                files.forEach(function (f, i) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        var wrap = document.createElement('div');
                        wrap.className = 'sl-photo-item';
                        var img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = f.name || ('Foto ' + (i + 1));
                        img.loading = 'lazy';
                        var remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'sl-photo-remove';
                        remove.setAttribute('data-sl-photo-remove', String(i));
                        remove.setAttribute('aria-label', 'Hapus ' + (f.name || ('foto ' + (i + 1))));
                        remove.textContent = '×';
                        var cap = document.createElement('div');
                        cap.className = 'caption';
                        cap.textContent = (f.name || ('Foto ' + (i + 1))) + (f.size ? ' (' + formatSize(f.size) + ')' : '');
                        wrap.appendChild(img);
                        wrap.appendChild(remove);
                        wrap.appendChild(cap);
                        preview.appendChild(wrap);
                    };
                    reader.readAsDataURL(f);
                });
            }
            function addFiles(list) {
                var incoming = Array.prototype.slice.call(list || []);
                if (!incoming.length) return;
                // UX saja: backend tetap validator utama (wajib 1-5, image, mimes jpg/jpeg/png/webp, 8 MB/file).
                clearError();
                incoming.forEach(function (f) {
                    if (files.length >= MAX_FILES) {
                        showError('Maksimal 5 foto laporan.');
                        return;
                    }
                    if (!validType(f)) {
                        showError('Format foto tidak didukung. Gunakan JPG, JPEG, PNG, atau WEBP.');
                        return;
                    }
                    if (f.size > MAX_SIZE) {
                        showError('Foto "' + (f.name || 'tanpa nama') + '" melebihi batas ukuran 8 MB.');
                        return;
                    }
                    files.push(f);
                });
                sync();
            }
            function cameraSupported() {
                return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
            }
            function stopCamera() {
                if (cameraStream) {
                    cameraStream.getTracks().forEach(function (track) { try { track.stop(); } catch (e) {} });
                    cameraStream = null;
                }
                if (camVideo) {
                    try { camVideo.pause(); } catch (e) {}
                    try { camVideo.srcObject = null; } catch (e) {}
                }
                if (camPanel) camPanel.hidden = true;
            }
            function openCamera() {
                if (cameraBtn && cameraBtn.disabled) {
                    showError('Maksimal 5 foto laporan.');
                    return;
                }
                if (!cameraSupported()) {
                    showError('Perangkat atau browser ini tidak mendukung kamera langsung. Silakan gunakan Pilih dari Galeri.');
                    return;
                }
                // Hindari stream ganda: hentikan stream lama sebelum start baru.
                stopCamera();
                clearError();
                var constraints = { video: { facingMode: { ideal: 'environment' } }, audio: false };
                navigator.mediaDevices.getUserMedia(constraints).then(function (stream) {
                    cameraStream = stream;
                    camVideo.srcObject = stream;
                    if (camPanel) camPanel.hidden = false;
                    var play = null;
                    try { play = camVideo.play(); } catch (e) {}
                    if (play && play.catch) play.catch(function () {});
                    if (camCapture) camCapture.focus();
                }).catch(function (e) {
                    stopCamera();
                    var name = (e && e.name) || '';
                    if (name === 'NotAllowedError' || name === 'SecurityError') {
                        showError('Kamera tidak dapat diakses. Silakan izinkan akses kamera pada pengaturan browser atau gunakan Pilih dari Galeri.');
                    } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
                        showError('Kamera tidak tersedia di perangkat ini. Silakan gunakan Pilih dari Galeri.');
                    } else if (name === 'NotReadableError') {
                        showError('Kamera sedang dipakai aplikasi lain. Tutup aplikasi tersebut lalu coba lagi, atau gunakan Pilih dari Galeri.');
                    } else {
                        showError('Kamera tidak dapat dibuka. Silakan gunakan Pilih dari Galeri.');
                    }
                });
            }
            function capturePhoto() {
                if (!cameraStream || !camVideo || !camCanvas) return;
                var w = camVideo.videoWidth || 0;
                var h = camVideo.videoHeight || 0;
                if (!w || !h) {
                    showError('Kamera belum siap. Tunggu pratinjau tampil lalu coba lagi.');
                    return;
                }
                camCanvas.width = w;
                camCanvas.height = h;
                var ctx = camCanvas.getContext('2d');
                if (!ctx) {
                    showError('Kamera tidak dapat mengambil foto di browser ini. Gunakan Pilih dari Galeri.');
                    return;
                }
                ctx.drawImage(camVideo, 0, 0, w, h);
                var done = function (blob) {
                    if (!blob) {
                        showError('Kamera gagal mengambil foto. Coba lagi atau gunakan Pilih dari Galeri.');
                        return;
                    }
                    var file = null;
                    try {
                        file = new File([blob], 'kamera-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                    } catch (e) {
                        showError('Browser ini tidak mendukung pengambilan foto kamera. Gunakan Pilih dari Galeri.');
                        return;
                    }
                    addFiles([file]);
                    // Kamera tetap aktif agar bisa "Ambil Foto Lagi"; tutup via Tutup Kamera.
                };
                if (camCanvas.toBlob) {
                    camCanvas.toBlob(done, 'image/jpeg', 0.92);
                } else {
                    showError('Browser ini tidak mendukung pengambilan foto kamera. Gunakan Pilih dari Galeri.');
                }
            }
            if (cameraBtn) {
                cameraBtn.addEventListener('click', function () {
                    openCamera();
                });
            }
            if (camCapture) {
                camCapture.addEventListener('click', function () {
                    capturePhoto();
                });
            }
            if (camClose) {
                camClose.addEventListener('click', function () {
                    stopCamera();
                    if (cameraBtn) cameraBtn.focus();
                });
            }
            if (galleryBtn) {
                galleryBtn.addEventListener('click', function () {
                    if (galleryBtn.disabled) return;
                    galleryInput.click();
                });
            }
            galleryInput.addEventListener('change', function () {
                addFiles(galleryInput.files);
                galleryInput.value = '';
            });
            // Lepas kamera saat user pindah halaman agar indikator mati & resource bebas.
            window.addEventListener('pagehide', stopCamera);
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) stopCamera();
            });
            if (preview) {
                preview.addEventListener('click', function (e) {
                    var btn = e.target && e.target.closest ? e.target.closest('[data-sl-photo-remove]') : null;
                    if (!btn) return;
                    var idx = parseInt(btn.getAttribute('data-sl-photo-remove'), 10);
                    if (!isNaN(idx) && idx >= 0 && idx < files.length) {
                        files.splice(idx, 1);
                        if (files.length === 0) {
                            if (count) count.textContent = '0 / 5 foto';
                        } else {
                            clearError();
                        }
                        sync();
                    }
                });
            }
            if (form) {
                var submitting = false;
                var submitBtn = form.querySelector('button[type="submit"]');
                var submitLabel = submitBtn ? submitBtn.innerHTML : '';
                form.addEventListener('submit', function (e) {
                    sync();
                    if (files.length < 1) {
                        e.preventDefault();
                        showError('Foto bukti wajib diisi (minimal 1 foto).');
                        return;
                    }
                    if (files.length > MAX_FILES) {
                        e.preventDefault();
                        showError('Maksimal 5 foto laporan.');
                        return;
                    }
                    // B1: cegah double-submit di frontend (backend tetap
                    // idempoten via idempotency_key). Tombol dikunci hanya
                    // bila request benar-benar dikirim; validasi gagal di
                    // atas selalu return sebelum penguncian.
                    if (submitting) {
                        e.preventDefault();
                        return;
                    }
                    submitting = true;
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.setAttribute('aria-disabled', 'true');
                        submitBtn.innerHTML = 'Mengirim…';
                    }
                    // Pengaman: bila navigasi gagal total (offline total),
                    // buka kunci setelah 15 detik agar user bisa coba lagi
                    // dengan key yang SAMA (aman: backend idempoten).
                    // Submit normal bernavigasi sehingga timeout tak terlihat.
                    setTimeout(function () {
                        if (document.visibilityState === 'visible' && submitting && submitBtn && submitBtn.disabled && !navigator.onLine) {
                            submitting = false;
                            submitBtn.disabled = false;
                            submitBtn.removeAttribute('aria-disabled');
                            submitBtn.innerHTML = submitLabel;
                        }
                    }, 15000);
                });
            }
            sync();
        })();
        </script>
        <div class="sl-form-actions">
            <button type="submit" class="btn">Kirim Laporan</button>
            <a href="{{ route('siswa.reports.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@endsection


