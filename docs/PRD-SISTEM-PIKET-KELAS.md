# PRD — Sistem Piket Kelas SI-LPiket

## 1. Judul, Versi, Tanggal, Tujuan, Ruang Lingkup

- **Judul:** Product Requirements Document — Sistem Piket Kelas SI-LPiket
- **Versi:** 1.0.0 (draft audit, belum disetujui)
- **Tanggal penyusunan:** 2026-10-16 (Asia/Jakarta)
- **Lokasi dokumen:** `docs/PRD-SISTEM-PIKET-KELAS.md` di proyek Laravel `C:\Projek SI_LPiket\si-lpiket`
- **Tujuan:** Mendefinisikan kebutuhan fitur Sistem Piket Kelas (penjadwalan piket per kelas, pengiriman laporan piket kelas harian, verifikasi/pengembalian/persetujuan oleh Guru Piket, dengan peran Admin sebagai pengelola jadwal) tanpa merusak alur laporan masalah siswa yang sudah berjalan.
- **Ruang lingkup (in-scope):**
  - Penjadwalan piket kelas oleh Admin.
  - Pengiriman satu laporan piket per kelas per tanggal.
  - Verifikasi, pengembalian, dan persetujuan laporan oleh Guru Piket yang bertugas.
  - Status, tanggal Asia/Jakarta, pencegahan ganda, lampiran, validasi, keamanan, UI responsif.
  - Integrasi dengan `classes`, `majors`, `students`, `teachers`, `users_baru`, `duty_schedules` yang sudah ada.
- **Out-of-scope tahap ini:**
  - Implementasi kode, migrasi database, perubahan data, instalasi paket.
  - Perubahan semantik alur laporan masalah siswa yang sudah berjalan (`menunggu → dalam_penanganan → diteruskan/selesai`).
  - Notifikasi WhatsApp/Fonnte untuk piket kelas kecuali disepakati (layanan `FonnteService` ada untuk kredensial akun, bukan untuk laporan).

> Klasifikasi: bagian 4 adalah **Fakta audit terverifikasi**. Bagian lain adalah **Usulan desain yang memerlukan persetujuan**, kecuali dinyatakan sebagai **Keputusan disepakati** atau **Belum diketahui**.

## 2. Latar Belakang Masalah dan Tujuan Bisnis

### 2.1 Latar belakang (fakta + asumsi yang dipisahkan)

- **Fakta:** SI-LPiket saat ini memiliki alur laporan masalah: Siswa membuat laporan (`siswa.reports.create/store`), Guru Piket mengambil (`claim`), mengambil alih (`takeover`), menyelesaikan (`complete`), meneruskan ke Kepala Sekolah (`forward`), Kepala Sekolah menyelesaikan (`complete`) atau mengembalikan (`returnToTeacher`). Status diverifikasi di `Report::status` dan `ReportHistory::action`.
- **Fakta:** Penjadwalan yang ada adalah **jadwal Guru Piket harian** (`duty_schedules`: `teacher_id`, `day` enum `senin|selasa|rabu|kamis|jumat|sabtu`), dikelola Admin via `Admin\DutyScheduleController` (`index/create/store/edit/update/destroy`). Tidak ada kolom jam operasional; `GuruDutyService` menegaskan penjadwalan bersifat harian.
- **Fakta:** Struktur kelas/jurusan/akun sudah ada: `majors`, `classes` (`major_id`, `name`, `status`), `students` (`user_id` unique, `nis` unique, `class_id`), `teachers` (`user_id` unique, `nip` unique), akun utama `users_baru` (`name`, `username` unique, `password` hashed, `role`, `status`).
- **Belum diketahui / perlu konfirmasi pengguna:** definisi operasional “Piket Kelas” yang diminta (apakah laporan kebersihan/ketertiban kelas harian oleh perwakilan kelas, apakah satu laporan per kelas per hari, siapa pelapor sah, apakah Guru Piket memverifikasi di hari yang sama).

### 2.2 Tujuan bisnis (usulan, perlu persetujuan)

1. Setiap kelas memiliki jadwal piket yang jelas dan terlihat oleh Siswa/Admin/Guru Piket.
2. Setiap kelas hanya mengirim satu laporan piket yang sah per tanggal (anti-ganda).
3. Guru Piket yang bertugas hari itu memverifikasi dan menyetujui/mengembalikan dengan jejak audit lengkap.
4. Tidak menambah beban Admin secara manual harian; jadwal bersifat berulang/mingguan.
5. Tidak merusak alur laporan masalah yang sudah berjalan dan sudah diuji (49 Feature tests terverifikasi ada).

## 3. Aktor dan Hak Akses Masing-Masing Role

### 3.1 Fakta peran yang ada (terverifikasi di `routes/web.php`, `EnsureRole`, `AuthController`)

| Role (`users_baru.role`) | Rute prefix | Fakta hak akses saat ini |
|---|---|---|
| `admin` | `/admin` | `DashboardController`, CRUD `users`, `majors`, `classes`, `students`, `teachers`, `duty-schedules`, `report-categories`, profil, notifikasi. Middleware `auth,role:admin`. |
| `siswa` | `/siswa` | Dashboard, `reports.index/create/store/show`, `attachment`, profil, notifikasi. Middleware `auth,role:siswa`. Pelapor adalah `Student` milik `request->user()`; `class_id` diambil dari data siswa, bukan input. |
| `guru` | `/guru` | Dashboard, `reports.index/show/attachment`, `claim/takeover/complete/forward`, profil, notifikasi. Akses objek laporan dibatasi (bukan semua laporan; di luar hak → 404). |
| `kepala_sekolah` | `/kepala-sekolah` | Dashboard, `monitoringJson`, `reports.index/show/attachment`, `complete/return`, profil, notifikasi. Hanya laporan `diteruskan` / pernah `forwarded` / `dalam_penanganan` hasil return (otorisasi `authorizeReport`). |

- **Fakta autentikasi:** login memakai `username` + `password` (`UserBaru`), cek `status='aktif'`, `SingleSession::tryClaim` (first-login-wins; device kedua ditolak), logout melepas marker sesi milik sesi itu. Middleware global: `TrimIdentityInput` (prepend web), `ValidateAuthenticatedUser` (append web), alias `role` → `EnsureRole`.
- **Fakta profil terkait:** `Teacher::resolveForUser` tidak membuat profil; bila tidak ada profil → `MissingTeacherProfile` → 403 terkendali. `Student` wajib ada dan punya `class_id`, bila tidak → 403.

### 3.2 Usulan hak akses Sistem Piket Kelas (perlu persetujuan, tidak mengubah tabel fakta di atas)

| Aktor | Usulan hak akses Piket Kelas |
|---|---|
| Admin | CRUD jadwal piket kelas; melihat semua laporan piket kelas; tidak boleh mengubah isi laporan siswa; tidak boleh menyetujui laporan (agar segregasi tugas terjaga). Alternatif bila disetujui: Admin boleh membatalkan jadwal duplikat. |
| Siswa (pelapor kelas) | Melihat jadwal piket kelasnya; membuat/mengirim satu laporan piket untuk kelas+tanggal yang menjadi haknya; melihat status laporannya; membaca lampiran milik laporannya. Tidak boleh melihat/mengubah laporan kelas lain. |
| Guru Piket (yang bertugas hari itu) | Melihat antrean laporan piket kelas pada hari tugasnya; verifikasi (setujui) / kembalikan dengan catatan; melihat lampiran setelah lolos otorisasi objek; tidak boleh menyetujui di luar hari tugasnya (kecuali kebijakan takeover disepakati). |
| Kepala Sekolah | **Di luar ruang lingkup default.** Tidak diberi aksi piket kelas kecuali pengguna meminta. Alasan: mencegah perluasan otorisasi tanpa persetujuan. |
| Peran kelas yang sudah ada (`class_representatives.type km/wkm`, `temporary_reporters`) | **Belum diputuskan.** Fakta: tabel dan model ada, tetapi tidak ada controller/route/view yang menggunakannya dalam audit ini (tidak ditemukan pemakaian di route dan controller laporan). Usulan: jadikan kandidat penentu “pelapor sah”, tetapi wajib diputuskan pengguna sebelum dipakai. Jangan mengasumsikan KM/WKM otomatis berhak. |

## 4. Kondisi Sistem Saat Ini Berdasarkan Hasil Audit

> Semua butir di bagian ini adalah **fakta terverifikasi** dari file yang dibaca. Bukan usulan.

### 4.1 Git dan lokasi

- Proyek aktif: `C:\Projek SI_LPiket\si-lpiket` (berisi `artisan`, `composer.json`, `routes/web.php`). Direktori induk `C:\Projek SI_LPiket` berisi folder `si-lpiket`.
- Git: branch `main`, `up to date with origin/main`, `working tree clean`, tidak ada perubahan tertunda. Commit terakhir terverifikasi: `38f6d34 tahap 6`.
- Folder `docs/` **tidak ada** sebelum audit ini; dibuat kosong selama tahap PRD (bukan perubahan aplikasi).
- File PRD `docs/PRD-SISTEM-PIKET-KELAS.md` **tidak ada** sebelum audit ini.
- File migrasi yang disebut di tab editor (`2026_09_29_024938_add_foreign_keys_to_si_lpiket_tables.php`) **tidak ditemukan** di `database/migrations` saat audit (daftar aktual hanya 9 file, lihat 4.4). Tidak boleh mengarang isinya.

### 4.2 Struktur aplikasi

- Framework: Laravel (`laravel/framework ^13.17`), PHP `^8.3` (dari `composer.json`). Tanpa paket tambahan terverifikasi selain dev default + `tinker`.
- Controller terverifikasi (via `glob app/**/*.php`):
  - `Admin`: `Dashboard, DutySchedule, Major, ReportCategory, SchoolClass, Student, Teacher, User, AccountWhatsapp` (9).
  - `Guru`: `Dashboard, Report` (2). `Siswa`: `Dashboard, Report` (2). `KepalaSekolah`: `Dashboard, Report` (2).
  - Umum: `Auth, Notification, Profile, Controller` (4). Middleware: `EnsureRole, TrimIdentityInput, ValidateAuthenticatedUser` (3).
- Model terverifikasi (15 file): `UserBaru, User, Teacher, Student, SchoolClass, Major, DutySchedule, Report, ReportAttachment, ReportCategory, ReportHistory, Notification, Setting, ClassRepresentative, TemporaryReporter`.
- Service/Support terverifikasi: `GuruDutyService` (penentu piket harian), `FonnteService` (WA kredensial), `SecureAttachment`, `ReportIdempotency`, `SingleSession`, `WaCredentialDispatch`, `HandlesNipUniqueViolation`.
- Route terverifikasi: `routes/web.php` 119 baris. Tidak ada route API piket kelas. Tidak ada route `piket-kelas` dalam bentuk apa pun (pencarian `piket` menemukan konteks Guru Piket, bukan Piket Kelas sebagai entitas tersendiri).
- Scheduler terverifikasi: `routes/console.php` hanya `notifications:cleanup` harian + perintah `inspire`. Tidak ada scheduler piket kelas.

### 4.3 Autentikasi, otorisasi, keamanan lampiran, idempotensi

- Login/logout/single-session seperti diringkas di 3.1 (terverifikasi di `AuthController.php`).
- Otorisasi objek laporan: Guru hanya `menunggu` (tanpa assignee) bila sedang piket, `dalam_penanganan` lanjutan bila `assigned_at < Carbon::today()` dan bukan miliknya, atau miliknya sendiri; selain itu 404. Kepsek hanya via `authorizeReport`.
- Lampiran: disk privat `local` di `reports/`, disajikan via endpoint `attachment` terproteksi per role. `SecureAttachment::resolve` menegakkan: tolak null byte, backslash, path absolut, prefix non-`reports/`, segmen `.`/`..`/kosong, panjang >255; verifikasi `realpath` + prefix root + file reguler; MIME dari isi file (allowlist `image/jpeg,png,webp`), header `nosniff`, `Content-Disposition` inline. Kegagalan → null → 404 generik.
- Validasi upload siswa (terverifikasi di `Siswa\ReportController@store`): `title` max 200, `category_id` harus `report_categories.status='aktif'`, `location` max 200, `description` wajib, `photos` wajib array 1–5, tiap file `image,mimes:jpg,jpeg,png,webp,max:8192` (8 MB). `class_id` dan `priority` berasal dari server (`student.class_id`, `category.priority`), bukan input pengguna.
- Idempotensi (terverifikasi di `ReportIdempotency` + `Siswa\ReportController@store`): `idempotency_key` UUID per render, format `[0-9a-fA-F-]{8,64}`, lock `Cache::lock(...)->block(10)`, fingerprint teks+nama/ukuran foto, replay redirect ke laporan sama bila key sama + fingerprint sama, tolak bila fingerprint berbeda, marker TTL 24 jam. Mencegah ganda akibat double-submit/refresh.
- Profil/password: `ProfileController@updatePassword` mewajibkan `current_password`, `min:8`, `confirmed`, `different:current_password`; ada notifikasi audit ke seluruh Admin aktif dalam transaksi yang sama.

### 4.4 Database (skema dari migrasi, bukan dari klaim jumlah data)

- Migrasi aktual (9 file, terverifikasi via glob):
  - `0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`
  - `2026_09_29_032134_create_si_lpiket_base_tables.php` (13 tabel SI-LPiket tanpa FK)
  - `2026_09_29_032500_add_foreign_keys_to_si_lpiket_tables.php` (daftar FK aktual; nama file tanpa timestamp `024938` seperti di tab tidak ditemukan)
  - `2026_09_29_033437_repoint_si_lpiket_user_fks_to_users_baru.php` (5 FK akun dari `users` → `users_baru`)
  - `2026_09_29_034754_align_si_lpiket_base_structure_fase1.php` (penyelarasan tipe, tanpa FK baru)
  - `2026_10_01_115116_create_settings_table.php`
  - `2026_10_13_000001_add_unique_to_teachers_nip.php`
- Skema kunci terverifikasi (tipe/enum/index dari migrasi base):
  - `users_baru`: `name`, `username` unique, `password`, `role enum(admin,siswa,guru,kepala_sekolah) nullable`, `status enum(aktif,nonaktif) nullable`.
  - `majors`: `name(200)`, `status(aktif,nonaktif)`.
  - `classes`: `major_id` (index), `name`, `status(aktif,nonaktif)`.
  - `students`: `user_id` unique, `nis(50)` unique, `class_id` (index), `phone` nullable.
  - `teachers`: `user_id` unique, `nip(50)` (+UNIQUE via migrasi 2026_10_13 bila prasyarat lolos), `phone` nullable.
  - `class_representatives`: `class_id`, `student_id`, `type enum(km,wkm)`.
  - `temporary_reporters`: `class_id`, `student_id`, `active_date(date)`, `activated_by`.
  - `duty_schedules`: `teacher_id` (index), `day enum(senin,s Selasa? — aktual: senin,selasa,rabu,kamis,jumat,sabtu)`.
  - `report_categories`: `name(200)`, `description` nullable, `status(aktif,nonaktif)`, `priority(rendah,sedang,tinggi)`.
  - `reports`: `student_id, class_id, category_id` (index), `title(200)`, `description`, `location(200)`, `priority enum`, `status enum(menunggu,dalam_penanganan,diteruskan,selesai)`, `assigned_teacher_id nullable`, `assigned_at/completed_at nullable`.
  - `report_attachments`: `report_id` (index), `file_patch`, `original_name`, `file_size`, `mime_type(200)`.
  - `report_histories`: `report_id`, `user_id`, `action enum(created,claimed,handled,forwarded,returned,completed)`, `from_status/to_status enum(menunggu,dalam_penanganan,diteruskan,selesai) nullable`, `note nullable`.
  - `notifications`: `user_id`, `report_id nullable`, `title(200)`, `message`, `is_read default false`.
  - FK terverifikasi (dari migrasi `032500` + repoint): students→users_baru/users_baru+classes; teachers→users_baru; class_representatives→classes/students; temporary_reporters→classes/students/users_baru; duty_schedules→teachers (RESTRICT); reports→students/classes/categories (RESTRICT), assigned_teacher→teachers (SET NULL); attachments→reports (CASCADE); histories→reports (CASCADE)/users_baru (RESTRICT); notifications→users_baru (CASCADE)/reports (SET NULL).
- **Fakta penting untuk PRD:** tidak ada tabel/kolom piket kelas (tidak ada `class_duty_schedules`, tidak ada `report_date`, tidak ada `class_report`). Tidak ada unique `(class_id, tanggal)` di mana pun. Jumlah baris data **tidak diaudit** (dilarang menyentuh DB pada tahap ini; tidak ada klaim jumlah data).
- **Fakta timezone config:** `config/app.php:68` → `'timezone' => env('APP_TIMEZONE','Asia/Jakarta')`; `.env.example:6` → `APP_TIMEZONE=Asia/Jakarta`. `GuruDutyService::TIMEZONE='Asia/Jakarta'`, `todayDay()` memetakan `dayOfWeek 1–6 → senin–sabtu`, `0 (Minggu) → null`. Perbandingan lanjutan memakai `Carbon::today()` dan `assigned_at < Carbon::today()`.

### 4.5 Blade/UI dan aset

- Layout per role terverifikasi: `layouts/admin,guru,siswa,kepala-sekolah`, komponen `app-shell`, badge (`status,state,priority`), tabel/search/filter/pagination admin, dropdown profil.
- Halaman terverifikasi: `admin.duty_schedules.{index,create,edit}`, `admin.{users,majors,classes,students,teachers,report_categories}.{index,create,edit}`, `siswa.reports.{index,create,show}`, `guru.reports.{index,show}`, `kepala-sekolah.reports.{index,show}`, dashboard per role, notifikasi per role, profil per role, `auth.login`.
- Aset terverifikasi: `public/css/si-lpiket.css` ada, `public/images/Logo-si-lpiket.jpg` ada (dirujuk login + app-shell). Form siswa memakai kamera/galeri + pratinjau + batas PHP (`upload_max_filesize`, `post_max_size`) sebagai catatan server.
- Tidak ada halaman piket kelas terverifikasi.

### 4.6 Pengujian

- `phpunit.xml`: suite `Unit` + `Feature`; env testing memaksa `DB sqlite :memory:`, `CACHE array`, `SESSION array`, `QUEUE sync`.
- Guard terverifikasi (`TestCase` + `TestDatabaseGuard`): tolak bila nama DB aktual `si_l_piket`; izinkan sqlite-memory dan MySQL `si_l_piket_test`; helper `useIsolatedTestDatabase()` untuk test MySQL terisolasi.
- 49 Feature tests + 1 Unit terverifikasi via glob (contoh relevan: `GuruReportStep1`, `GuruHandleStep2`, `GuruTakeoverStep3`, `SiswaReportStep1/Step2`, `KepalaSekolahReportStep1`, `KepalaSekolahHandleStep2`, `KepalaSekolahScope`, `KepsekMonitoring`, `Notification*` (claim/complete/forward/return/read), `ReportIdempotency`, `SecureAttachmentB35`, `AttachmentSecurityFase141/142`, `PrivateDiskServeB37`, `AdminDutySchedulesFase12D`, `AdminMasterDataFase12F`, `SecurityFase14`, `SingleSession`, `StaleSessionAuth`, `FinalIntegrationUatFase13`).
- Hasil eksekusi pengujian **tidak diklaim** pada tahap ini (dilarang menjalankan migrasi/DB; PRD tidak mengarang hasil test).

## 5. Kebutuhan Fungsional dan Nonfungsional

### 5.1 Kebutuhan fungsional (usulan, perlu persetujuan; kode FR-PK-xxx hanya penomoran PRD)

- **FR-PK-01 (Jadwal piket kelas):** Admin dapat membuat, melihat, mengubah, menghapus jadwal piket kelas berulang mingguan (hari `senin–jumat`, Minggu libur mengikuti pola `duty_schedules`). Satu baris jadwal = satu kelas pada satu hari. Sistem menolak duplikat `(class_id, day)` dengan pesan jelas.
- **FR-PK-02 (Visibilitas jadwal):** Siswa melihat jadwal piket kelasnya; Guru Piket melihat jadwal kelas yang piket pada hari tugasnya; Admin melihat/mencari/menyaring semua jadwal.
- **FR-PK-03 (Pengiriman laporan piket):** Siswa yang berhak dapat mengirim satu laporan piket untuk (`class_id`, `tanggal`) yang menjadi haknya. Field minimal: judul, kategori aktif (bila dipakai), lokasi detail, deskripsi/isi, foto bukti 1–5 (mengikuti pola validasi laporan masalah). `class_id` dan tanggal ditentukan server, bukan input bebas.
- **FR-PK-04 (Verifikasi):** Guru Piket yang bertugas pada tanggal laporan dapat menyetujui laporan dengan catatan wajib. Status berubahตาม aturan bagian 9.
- **FR-PK-05 (Pengembalian):** Guru Piket dapat mengembalikan laporan dengan catatan wajib; Siswa dapat mengirim ulang/perbaikan (satu baris laporan yang sama, bukan laporan baru) selama jendela yang disepakati.
- **FR-PK-06 (Persetujuan final):** Persetujuan Guru Piket adalah final untuk piket kelas (tidak diteruskan ke Kepala Sekolah secara default). Alternatif bila diminta: teruskan opsional; wajib diputuskan.
- **FR-PK-07 (Riwayat):** Setiap perubahan status piket kelas tercatat di histori (siapa, kapan Asia/Jakarta, dari→ke, catatan).
- **FR-PK-08 (Notifikasi dalam aplikasi):** Siswa mendapat notifikasi saat laporan disetujui/dikembalikan; Guru Piket mendapat notifikasi saat laporan piket baru masuk pada hari tugasnya. Pola mengikuti `notifications` yang ada (cek duplikat per `(user_id, report_id, title)`).
- **FR-PK-09 (Lampiran aman):** Foto disimpan di disk privat, disajikan via endpoint terotorisasi dengan proteksi `SecureAttachment` yang sama.

### 5.2 Kebutuhan nonfungsional (usulan)

- **NFR-PK-01 Keamanan:** Otorisasi ganda (middleware role + otorisasi objek per laporan + 404 generik untuk yang tak berhak); `class_id`/tanggal tidak可di-tamper via input; file hanya gambar, maks 8 MB; path dikungkung; single-session tetap berlaku.
- **NFR-PK-02 Integritas tanggal:** Seluruh penentuan hari/tanggal memakai `Asia/Jakarta`; pergantian hari memakai awal hari Jakarta (lihat bagian 10).
- **NFR-PK-03 Anti-ganda:** Unique DB `(class_id, tanggal)` + validasi aplikasi + proteksi race (transaksi/lock) + idempotency key untuk double-submit (lihat bagian 11).
- **NFR-PK-04 Kinerja:** Daftar terjadwal + paginasi 10 (mengikuti pola `paginate(10)`); index pada `(class_id, tanggal)` dan `(status, tanggal)`; N+1 dicegah via eager load.
- **NFR-PK-05 Aksesibilitas/UI:** Lihat bagian 12.
- **NFR-PK-06 Keterujian:** Setiap aturan status/tanggal/ganda memiliki Feature test terisolasi (`si_l_piket_test` atau sqlite-memory) tanpa menyentuh `si_l_piket`.
- **NFR-PK-07 Kompatibilitas:** Tidak mengubah kolom/tabel laporan masalah; tidak mengubah route/controller/model laporan masalah; migrasi baru harus idempoten dan non-destruktif.

## 6. Alur Kerja Admin, Siswa, dan Guru Piket

### 6.1 Alur Admin (usulan)

1. Admin membuka `Admin → Jadwal Piket Kelas` (halaman baru, diusulkan).
2. Admin memilih kelas (dari `classes` aktif) + hari (`senin–jumat`) → simpan. Sistem menolak duplikat `(class_id, day)`.
3. Admin dapat mengubah/menghapus baris jadwal. Penghapusan hanya menghapus baris jadwal, tidak menghapus laporan/histori/lampiran (mengikuti pola `DutyScheduleController@destroy`).
4. Admin memantau laporan piket kelas (read-only) bila disepakati.

### 6.2 Alur Siswa (usulan; alur laporan masalah lama tidak diubah)

1. Siswa login → membuka `Siswa → Piket Kelas` (halaman baru).
2. Sistem menampilkan: apakah kelasnya piket hari ini (Asia/Jakarta), apakah sudah ada laporan untuk (`class_id`, hari ini), dan siapa yang boleh melapor (aturan pelapor sah, bagian 7 — perlu persetujuan).
3. Siswa yang berhak mengisi form (judul, kategori bila dipakai, lokasi, deskripsi, foto 1–5) + `idempotency_key` → kirim.
4. Sistem membuat satu laporan piket berstatus awal (diusulkan `menunggu_verifikasi`, lihat bagian 9) atau menolak ganda dengan pesan + tautan ke laporan yang sudah ada.
5. Siswa memantau status; bila dikembalikan, siswa memperbaiki dalam laporan yang sama (bukan membuat baru).

### 6.3 Alur Guru Piket (usulan)

1. Guru Piket login pada hari tugasnya (ditentukan `duty_schedules` + `GuruDutyService::todayDay()`).
2. Guru membuka `Guru → Piket Kelas` (halaman baru): antrean `menunggu_verifikasi` untuk tanggal hari ini (dan/atau yang belum selesai sesuai kebijakan).
3. Guru membuka detail → memeriksa foto/isi → **Setujui** (catatan wajib, status → `disetujui`) atau **Kembalikan** (catatan wajib, status → `dikembalikan`).
4. Setiap aksi menulis histori + notifikasi ke siswa pelapor.
5. Di luar hari tugas: daftar disembunyikan dan tombol aksi dinonaktifkan (mengikuti pola `guru.reports.index`: `$onDutyDay` → sembunyikan + nonaktifkan).

## 7. Aturan Penjadwalan Piket Kelas

### 7.1 Fakta acuan (tidak boleh diubah tanpa persetujuan)

- Hari valid sistem: `senin,selasa,rabu,kamis,jumat`; Minggu tidak terjadwal (`todayDay() → null`).
- Penentu tugas guru: `GuruDutyService::isOnDutyDay(teacher)` = ada baris `duty_schedules` untuk `teacher` pada hari Jakarta hari ini.
- Kelas memiliki `status aktif/nonaktif`; siswa terikat `class_id`; guru terikat `teacher_id`.

### 7.2 Usulan aturan (perlu persetujuan)

1. **Entitas jadwal baru** (diusulkan, lihat bagian 15): satu baris = (`class_id`, `day`). Unik `(class_id, day)`. Hanya kelas `aktif` boleh dijadwalkan. Hari hanya `senin–jumat`.
2. **Sifat berulang mingguan:** jadwal berlaku setiap minggu sampai diubah/dihapus. Tidak ada jadwal sekali-pakai pada tahap awal (agar Admin tidak input harian).
3. **Satu kelas satu hari? atau boleh beberapa kelas per hari?** Usulan default: **boleh beberapa kelas piket pada hari yang sama** (mis. kelas berbeda bergiliran area), tetapi **satu kelas tidak boleh dua baris pada hari yang sama**. Bila pengguna ingin satu kelas per hari secara global, wajib dinyatakan eksplisit (akan menambah unique `day`).
4. **Minggu libur:** tidak ada piket kelas pada Minggu; sistem menampilkan “di luar jadwal”.
5. **Pelapor sah (wajib diputuskan, opsi):**
   - Opsi A (disarankan bila struktur KM/WKM dipakai): hanya `class_representatives` (`km/wkm`) atau `temporary_reporters` yang `active_date = hari ini` untuk kelas itu.
   - Opsi B (sederhana): seluruh siswa kelas boleh melapor, tetapi baris pertama yang sukses mengunci (`class_id`, tanggal); pengirim berikutnya ditolak ganda.
   - Opsi C: Admin menunjuk pelapor harian. (Beban Admin tinggi; tidak disarankan.)
   - **Rekomendasi PRD:** pilih A atau B sebelum implementasi; jangan menggabungkan tanpa aturan prioritas.
6. **Perubahan jadwal tidak retroaktif:** mengubah/menghapus jadwal hanya memengaruhi tanggal berjalan ke depan; laporan yang sudah ada tidak berubah statusnya.

## 8. Aturan Pengiriman, Verifikasi, Pengembalian, dan Persetujuan Laporan

### 8.1 Fakta alur lama yang dipertahankan (tidak diubah)

- Pengiriman siswa memakai transaksi DB + idempotency + notifikasi ke Guru Piket hari itu dalam transaksi yang sama.
- Klaim guru memakai update atomik `where(status='menunggu', assigned_teacher_id null)` → `dalam_penanganan`; gagal → “sudah diambil”.
- Complete/forward/return memakai update bersyarat + histori + notifikasi dalam transaksi yang sama.
- Semua otorisasi gagal → 404 generik (bukan 403 bocor-info) untuk objek laporan, kecuali profil yatim → 403 terkendali.

### 8.2 Usulan aturan piket kelas (perlu persetujuan)

1. **Pengiriman:** hanya bila (a) kelas terjadwal pada hari Jakarta itu ATAU kebijakan “laporan insidental” disetujui, (b) pelapor sah menurut 7.2.5, (c) belum ada laporan sukses untuk (`class_id`, tanggal). Validasi file mengikuti 4.3. `idempotency_key` wajib untuk cegah double-submit.
2. **Verifikasi (oleh Guru Piket hari itu):** membaca detail + lampiran via endpoint terotorisasi; wajib memberi catatan (maks 2000, mengikuti pola complete/forward/return).
3. **Pengembalian:** status → `dikembalikan`, histori `returned`, notifikasi ke pelapor. Siswa memperbaiki dalam laporan yang sama; versi perbaikan menambah histori, bukan baris baru.
4. **Persetujuan:** status → `disetujui` (final), histori `completed/approved`, notifikasi ke pelapor. Tidak ada `diteruskan` ke Kepsek secara default.
5. **Larangan:** Guru yang tidak bertugas hari itu tidak dapat verifikasi/menyetujui/mengembalikan (tombol nonaktif + penolakan server). Siswa tidak dapat menyetujui laporannya sendiri via manipulasi ID (otorisasi objek + kepemilikan `student_id`/`class_id` dicek server).
6. **Transaksionalitas:** pembuatan laporan + lampiran + histori `created` + notifikasi guru dalam satu transaksi; aksi guru + histori + notifikasi siswa dalam satu transaksi.

## 9. Definisi Seluruh Status dan Kondisi Perubahannya

### 9.1 Fakta status laporan masalah (tidak diubah)

- `reports.status`: `menunggu → dalam_penanganan → diteruskan/selesai`. `dalam_penanganan → dalam_penanganan` dimungkinkan saat takeover (ganti assignee, histori `claimed`).
- `report_histories.action`: `created, claimed, handled, forwarded, returned, completed`; `from_status/to_status` memakai enum status yang sama.

### 9.2 Usulan status piket kelas (W AJIB diputuskan; dua opsi, pilih satu)

> PRD tidak mencampur status lama dan baru dalam satu kolom tanpa keputusan. Opsi di bawah memengaruhi desain DB (bagian 15).

**Opsi 1 (disarankan): tabel/entitas baru dengan status sendiri — tidak menyentuh enum `reports.status`.**

| Status | Arti | Dari → Ke | Pelaku | Syarat |
|---|---|---|---|---|
| `menunggu_verifikasi` | Laporan terkirim, antre verifikasi | (dibuat) → `menunggu_verifikasi` | Siswa sah | Lolos 8.2.1 + anti-ganda |
| `dikembalikan` | Perlu perbaikan | `menunggu_verifikasi` → `dikembalikan` | Guru Piket hari itu | Catatan wajib |
| `menunggu_verifikasi` (lagi) | Perbaikan dikirim ulang | `dikembalikan` → `menunggu_verifikasi` | Siswa pelapor asal | Dalam jendela yang disepakati; tetap satu baris |
| `disetujui` | Final, sah | `menunggu_verifikasi` → `disetujui` | Guru Piket hari itu | Catatan wajib; final |

**Opsi 2 (tidak disarankan): pakai ulang `reports.status`.** Harus memetakan (`menunggu` = antre, `dalam_penanganan` = diverifikasi?, `selesai` = disetujui) dan berisiko mengaburkan metrik laporan masalah + merusak filter yang ada. Hanya dipilih bila pengguna menolak tabel baru dengan alasan tertulis.

- **Kondisi perubahan (berlaku untuk Opsi 1):** setiap transisi memakai update bersyarat (`where(status=...)`) + histori + notifikasi dalam satu transaksi; bila `updated===0` → pesan “laporan sudah berubah/diproses pihak lain”.
- **Status jadwal/kelas yang dipakai:** `classes.status aktif/nonaktif`, `users_baru.status aktif/nonaktif` tetap menjadi prasyarat (akun/kelas nonaktif tidak dapat berpartisipasi).

## 10. Aturan Tanggal dan Pergantian Hari Menggunakan Timezone Asia/Jakarta

### 10.1 Fakta terverifikasi

- Default app `Asia/Jakarta` (`config/app.php`, `.env.example`).
- `GuruDutyService::now() = Carbon::now('Asia/Jakarta')`; `todayDay()` dari `dayOfWeek` Jakarta; Minggu → null.
- Lanjutan/takeover memakai `assigned_at < Carbon::today()` (awal hari server). Dashboard menampilkan `serverNow` + `WIB`.

### 10.2 Usulan aturan piket kelas (perlu persetujuan)

1. **Sumber kebenaran tanggal:** server (`Carbon::now('Asia/Jakarta')`), bukan tanggal dari klien/browser. Kolom tanggal laporan bertipe `date` (tanpa jam) yang diisi server dari `now('Asia/Jakarta')->toDateString()`.
2. **Pergantian hari:** pukul `00:00:00 Asia/Jakarta`. Laporan yang dibuat `23:59` masuk tanggal itu; `00:00` masuk tanggal berikutnya. Tidak ada grace period kecuali disepakati (bila ada, tetapkan mis. 15 menit dan tulis di PRD final).
3. **Penentuan “piket hari ini”:** `todayDay()` Jakarta dicocokkan ke jadwal (`class_duty` + `duty_schedules` guru). Pada Minggu: tidak ada piket; form pengiriman ditolak/dinonaktifkan dengan pesan “di luar jadwal”.
4. **Audit waktu:** `created_at/updated_at` tetap UTC-vs-local mengikuti Laravel; kolom `tanggal` (date Jakarta) adalah penentu anti-ganda, bukan `created_at`.
5. **Pengujian tanggal:** memakai `Carbon::setTestNow(Carbon::today()->setTime(...))` + `GuruDutyService::todayDay()` seperti pola test yang ada; wajib mencakup kasus batas tengah malam dan Minggu.

## 11. Pencegahan Laporan Ganda per Kelas per Tanggal

### 11.1 Fakta proteksi yang sudah ada (dapat dipakai ulang polanya)

- Idempotency key + lock + fingerprint + replay (bagian 4.3).
- Update atomik bersyarat (`where` status/assignee) untuk klaim/takeover/complete/forward/return.
- Unique DB: `students.user_id`, `students.nis`, `teachers.user_id`, `teachers.nip`, `users_baru.username`.

### 11.2 Usulan lapisan pencegahan (semuanya perlu diimplementasikan bila Opsi 1 dipilih)

1. **Unique DB:** `UNIQUE (class_id, tanggal)` pada entitas piket kelas. Ini jaring akhir; pelanggaran → pesan “laporan kelas hari ini sudah ada” + tautan ke laporan itu (bukan 500).
2. **Validasi aplikasi:** sebelum insert, cek `exists(class_id, tanggal)`; bila ada → tolak dengan info pemilik/waktu (tanpa membocorkan data sensitif kelas lain).
3. **Proteksi race:** transaksi + lock per `(class_id, tanggal)` (mis. `Cache::lock` seperti pola `ReportIdempotency` atau `SELECT ... FOR UPDATE` — pilih satu saat desain teknis; PRD tidak memaksakan salah satunya sebelum disetujui).
4. **Idempotency double-submit:** `idempotency_key` wajib seperti form siswa; retry dengan key sama → replay ke laporan yang sama.
5. **Otorisasi anti-tamper:** `class_id` diambil dari jadwal/kepemilikan server, bukan dari hidden input yang dapat diubah.
6. **Kriteria uji ganda:** dua request paralel `(class_id, tanggal)` sama → tepat satu sukses; refresh/resubmit → tidak membuat baru; payload berbeda + key sama → ditolak.

## 12. Kebutuhan Halaman, UI/UX, dan Responsivitas

### 12.1 Fakta UI yang ada (acuan konsistensi)

- `app-shell` + sidebar per role + breadcrumb + badge + tabel admin (search/filter/pagination) + notifikasi + profil. CSS `si-lpiket.css`, logo `Logo-si-lpiket.jpg`. Sidebar guru menyembunyikan daftar + menonaktifkan tombol saat tidak piket.

### 12.2 Usulan halaman baru (perlu persetujuan; tidak ada yang sudah ada)

| Halaman | Pengguna | Isi minimal |
|---|---|---|
| Admin → Jadwal Piket Kelas (`index`) | Admin | Tabel per hari (`senin–jumat`), search kelas, filter hari, pagination, aksi tambah/ubah/hapus. |
| Admin → Jadwal Piket Kelas (`create/edit`) | Admin | Pilih kelas aktif + hari; validasi duplikat inline; konfirmasi hapus (“hanya jadwal, laporan tetap”). |
| Siswa → Piket Kelas (`index`) | Siswa | Status “kelas piket hari ini / bukan”, status laporan hari ini, riwayat laporan kelasnya, tombol kirim bila berhak. |
| Siswa → Piket Kelas (`create/show`) | Siswa | Form seperti `siswa.reports.create` (kamera/galeri, pratinjau, batas 1–5 foto) + detail + histori + notifikasi hasil. |
| Guru → Piket Kelas (`index/show`) | Guru Piket | Antrean hari tugas, filter status, detail + lampiran + tombol Setujui/Kembalikan (catatan wajib) + histori. |

### 12.3 Usulan UX/responsivitas

- Mobile-first (siswa memotret via HP): tombol kamera/galeri besar, pratinjau, hitung `n/5 foto`, pesan batas PHP bila upload ditolak diam-diam.
- Nonaktifkan tombol + sembunyikan daftar saat tidak berhak (pola guru), tetapi tetap berikan pesan jelas (“Anda tidak terjadwal piket hari ini” / “Kelas Anda tidak piket hari ini”).
- Bahasa Indonesia, format tanggal Indonesia + `WIB`, konfirmasi sebelum hapus/setujui/kembalikan.
- Aksesibilitas minimal: label form, `aria-live` untuk hitung foto, kontras badge status.

## 13. Integrasi dengan Struktur Kelas, Jurusan, dan Akun yang Sudah Ada

### 13.1 Fakta integrasi yang wajib dihormati

- `classes.major_id → majors.id` (RESTRICT), `students.class_id → classes.id` (RESTRICT), `teachers.user_id → users_baru.id` (CASCADE), `duty_schedules.teacher_id → teachers.id` (RESTRICT).
- Hapus guru diblokir bila masih punya jadwal piket (`TeacherController`); hapus akun guru menghapus jadwalnya tetapi mempertahankan laporan/histori (`UserController`).
- Kategori laporan hanya yang `aktif` yang dapat dipakai; `priority` laporan mengikuti `category.priority` (server-side).

### 13.2 Usulan integrasi (perlu persetujuan)

- Jadwal piket kelas merujuk `classes.id` (hanya `status='aktif'`); menghapus/menonaktifkan kelas yang masih terjadwal harus ditolak/dikonfirmasi (pilih satu).
- Pelapor merujuk `students.id` + `users_baru.id`; akun `nonaktif` tidak dapat melapor; pindah kelas mengikuti `students.class_id` terkini (laporan lama tetap pada `class_id` saat dibuat).
- Verifikator merujuk `teachers.id` yang memiliki `duty_schedules` pada hari laporan (Jakarta). Guru tanpa profil → 403 terkendali (pola `resolveForUser`).
- Jurusan (`majors`) hanya untuk tampilan/filter (mis. “XII RPL 1 — RPL”), bukan penentu hak.
- `class_representatives` / `temporary_reporters` hanya dipakai bila Opsi A (7.2.5) dipilih; bila tidak, tidak disentuh.

## 14. Kebutuhan Lampiran, Validasi, Keamanan, dan Hak Akses

### 14.1 Usulan lampiran (mengikuti fakta 4.3)

- Wajib 1–5 foto, tiap `image,mimes:jpg,jpeg,png,webp,max:8192`;多久? — tetap 8 MB per file.
- Simpan di disk privat (mis. `reports/` atau subdirektori piket kelas yang masih di bawah `reports/` agar `SecureAttachment` tetap berlaku tanpa perubahan logika — perlu diputuskan).
- Sajikan hanya via endpoint terotorisasi dengan `SecureAttachment::resolve/response`; tanpa symlink publik.

### 14.2 Usulan validasi

- Server-side: judul max 200, lokasi max 200, deskripsi wajib, kategori (bila dipakai) harus `aktif`, `idempotency_key` format valid, `class_id`/tanggal dari server, catatan guru wajib max 2000.
- Client-side: pratinjau + hitung foto + cegah double-submit (disable tombol setelah klik + idempotency key), tetapi tidak menggantikan validasi server.

### 14.3 Usulan keamanan dan hak akses

- Middleware `auth` + `role:...` pada semua route piket kelas; otorisasi objek per laporan (pemilik/kelas/guru-tugas); kegagalan → 404 generik.
- Anti-IDOR: `show/attachment/approve/return` memverifikasi kepemilikan/kelas/hari-tugas di server; tidak mengandalkan URL.
- Single-session, trim identitas, validasi akun (`aktif`), dan proteksi path/MIME yang ada tetap berlaku tanpa pengecualian.
- Rate-limit pengiriman (mis. bawaan Laravel) dipertimbangkan saat desain teknis; tidak diputuskan di PRD ini.

## 15. Dampak terhadap Database

> **Status: USULAN, belum disetujui. Dilarang menjalankan migrasi pada tahap PRD.**

### 15.1 Opsi yang diusulkan (pilih satu sebelum implementasi)

**Opsi A (disarankan): dua tabel baru + tanpa ubah tabel lama.**

1. `class_duty_schedules` (nama diusulkan; alternatif: `class_piket_schedules` — pilih satu):
   - Alasan: jadwal piket kelas berulang mingguan belum ada di mana pun; `duty_schedules` adalah jadwal guru, tidak dapat dipakai ulang tanpa merusak semantik.
   - Kolom diusulkan: `id`, `class_id` (FK → `classes.id`, RESTRICT), `day` (enum `senin–jumat`, sama seperti `duty_schedules`), `timestamps`, `UNIQUE (class_id, day)`.
   - Alternatif yang ditolak: menambah `class_id` ke `duty_schedules` (mencampur dua domain jadwal dalam satu tabel; merusak query guru + index + validasi duplikat).
2. `class_duty_reports` (nama diusulkan; alternatif: `piket_kelas_reports`):
   - Alasan: laporan piket harian per kelas per tanggal belum ada; memakai ulang `reports` akan mencampur status/TTD/history/notifikasi laporan masalah dan berisiko regresi.
   - Kolom diusulkan: `id`, `class_id` (FK → `classes.id`, RESTRICT), `report_date` (`date`, diisi server dari Jakarta), `student_id` (FK → `students.id`, RESTRICT; pelapor), `title(200)`, `description`, `location(200)`, `category_id` nullable (FK → `report_categories.id`, bila kategori dipakai) atau tanpa kategori (perlu diputuskan), `status` (enum baru `menunggu_verifikasi,dikembalikan,disetujui`), `verified_by_teacher_id` nullable (FK → `teachers.id`, SET NULL), `verified_at` nullable, `timestamps`, `UNIQUE (class_id, report_date)`, index `(status, report_date)`.
   - Histori: diusulkan `class_duty_report_histories` (`report_id`, `user_id`, `action`, `from_status`, `to_status`, `note`) mengikuti pola `report_histories`; alternatif: memakai `report_histories` dengan `report_id` polimorfik — **ditolak** karena FK `report_histories.report_id → reports.id` akan rusak.
   - Lampiran: diusulkan `class_duty_report_attachments` dengan struktur sama seperti `report_attachments` ATAU kolom `report_type` — **perlu diputuskan**; default PRD: tabel terpisah agar FK CASCADE bersih dan `SecureAttachment::DIRECTORY` tetap `reports/` (subdirektori `reports/piket-kelas/` masih lolos prefix bila diputuskan demikian; bila direktori berbeda, `SecureAttachment` wajib diubah — nyatakan eksplisit).
   - Notifikasi: pakai ulang `notifications` dengan `report_id` nullable? **Tidak disarankan** karena FK ke `reports`. Usulan default: tambah kolom `class_duty_report_id` nullable ATAU tabel notifikasi terpisah — wajib diputuskan; jangan memaksa `report_id` untuk entitas berbeda.

**Opsi B (tidak disarankan): tambah kolom ke tabel lama.**

- Tambah `report_date` + unique `(class_id, report_date)` + status baru ke `reports`. Alasan penolakan: mengubah enum `status` + menambah kolom wajib pada alur yang sudah diuji; risiko regresi pada 49 Feature tests; mencampur metrik laporan masalah vs piket kelas; FK/history/notifikasi menjadi ambigu.

### 15.2 Prinsip migrasi (bila disetujui)

- Idempoten (`hasTable/hasColumn` guard), non-destruktif (tanpa `migrate:fresh/refresh`, `db:wipe`, `DROP`, `TRUNCATE`), FK eksplisit dengan rule (`RESTRICT` untuk referensi master, `CASCADE` untuk anak laporan, `SET NULL` untuk verifikator), tanpa ubah tabel lama pada Opsi A.
- Verifikasi: `php artisan migrate --pretend` / review SQL + test terisolasi sebelum menyentuh DB pengembangan; tidak pernah ke `si_l_piket` produksi dari test.

## 16. Kriteria Penerimaan yang Dapat Diuji

> Semua kriteria di bawah adalah **usulan**; nomor AC-PK-xxx hanya penomoran PRD.

- **AC-PK-01:** Admin membuat jadwal (`kelas aktif`, `senin–jumat`) → sukses + terlihat di daftar. Duplikat `(class_id, day)` → ditolak dengan pesan jelas, tidak ada baris baru.
- **AC-PK-02:** Siswa yang kelasnya piket hari ini (Jakarta) dan berhak melapor dapat membuka form; siswa kelas lain / di luar jadwal / akun nonaktif → ditolak/dinonaktifkan dengan pesan jelas.
- **AC-PK-03:** Pengiriman valid (1–5 foto sesuai aturan) → satu laporan `menunggu_verifikasi` + histori `created` + notifikasi ke Guru Piket hari itu. `class_id`/tanggal tercatat dari server.
- **AC-PK-04:** Kirim dua kali paralel `(class_id, tanggal)` sama → tepat satu sukses; yang lain menerima pesan ganda + tautan ke laporan yang ada. Refresh/resubmit dengan key sama → replay, tidak membuat baru.
- **AC-PK-05:** Minggu (Jakarta) → form ditolak/dinonaktifkan (“di luar jadwal”); tidak ada laporan Minggu kecuali kebijakan insidental disetujui.
- **AC-PK-06:** Guru Piket yang bertugas hari itu dapat menyetujui dengan catatan → `disetujui` + histori + notifikasi siswa. Tanpa catatan → ditolak validasi.
- **AC-PK-07:** Guru Piket dapat mengembalikan dengan catatan → `dikembalikan` + histori + notifikasi; siswa dapat mengirim perbaikan dalam laporan yang sama → kembali `menunggu_verifikasi` + histori.
- **AC-PK-08:** Guru yang tidak bertugas hari itu → daftar disembunyikan + aksi ditolak server. Siswa tidak dapat menyetujui/mengembalikan (otorisasi objek).
- **AC-PK-09:** Akses langsung via URL ke laporan kelas lain / lampiran tanpa hak → 404 generik; path traversal / MIME non-gambar → 404.
- **AC-PK-10:** Batas tengah malam Jakarta: laporan `23:59` vs `00:00` masuk tanggal yang benar; tidak ada duplikat lintas hari.
- **AC-PK-11:** Alur laporan masalah lama tetap lolos regresi (bagian 17) tanpa perubahan perilaku.
- **AC-PK-12:** UI responsif seluler (kamera/galeri, pratinjau, `n/5`) dan bahasa Indonesia + `WIB` konsisten.

## 17. Skenario Pengujian dan Regresi Fitur Lama

### 17.1 Skenario pengujian piket kelas (usulan, terisolasi `si_l_piket_test`/sqlite-memory)

1. Jadwal: buat, duplikat `(class_id, day)`, hari Minggu ditolak, kelas nonaktif ditolak, ubah, hapus (pastikan laporan tetap).
2. Pengiriman: valid, tanpa foto ditolak, >5 foto ditolak, file non-gambar ditolak, >8 MB ditolak, kategori nonaktif ditolak (bila kategori dipakai), pelapor tak berhak ditolak.
3. Anti-ganda: paralel, resubmit, key dipakai-ulang beda isi, kelas berbeda tanggal sama lolos, kelas sama tanggal beda lolos.
4. Tanggal: `setTestNow` batas tengah malam, Minggu null, zona Jakarta vs UTC.
5. Guru: setujui/kembalikan tanpa catatan ditolak; guru luar tugas ditolak; siswa self-approve ditolak; IDOR antar-kelas 404.
6. Histori/notifikasi: setiap transisi menulis histori + notifikasi tepat satu (tidak ganda); isi notifikasi benar (penerima, judul, tautan).
7. Lampiran: traversal, MIME palsu, prefix non-`reports/`, file hilang → 404; foto sah tampil inline.

### 17.2 Regresi fitur lama (wajib, tanpa kecuali)

- Jalankan suite yang sudah ada tanpa modifikasi perilaku: `SiswaReportStep1/Step2`, `GuruReportStep1`, `GuruHandleStep2`, `GuruTakeoverStep3`, `KepalaSekolahReportStep1`, `KepalaSekolahHandleStep2`, `KepalaSekolahScope`, `KepsekMonitoring`, `Notification*`, `ReportIdempotency`, `AttachmentSecurity*`, `PrivateDiskServeB37`, `SecureAttachmentB35`, `AdminDutySchedulesFase12D`, `AdminMasterData*`, `SecurityFase14`, `SingleSession/StaleSessionAuth`, `FinalIntegrationUatFase13`.
- Guard: tidak ada test yang menyentuh `si_l_piket`; setiap reconnect MySQL wajib `assertTestDatabaseSafe()` / `useIsolatedTestDatabase()`.
- Hasil test **belum dijalankan** pada tahap PRD; eksekusi dilakukan setelah persetujuan, sebelum dan sesudah implementasi.

## 18. Risiko, Dependensi, Keputusan yang Sudah Disepakati, dan Pertanyaan yang Masih Terbuka

### 18.1 Risiko (usulan mitigasi)

| Risiko | Dampak | Mitigasi usulan |
|---|---|---|
| Memakai ulang `reports.status` | Regresi laporan masalah, metrik rusak | Pilih Opsi A (tabel baru); tolak Opsi B kecuali alasan tertulis |
| Race laporan ganda | Duplikat `(class_id, tanggal)` | Unique DB + lock/transaksi + idempotency; uji paralel |
| Tanggal klien vs server | Laporan masuk tanggal salah | Tanggal selalu dari server Jakarta; uji batas tengah malam |
| Pelapor sah tak jelas | Sengketa “siapa boleh melapor” | Putuskan 7.2.5 sebelum koding; tulis di PRD final |
| Scope merayap ke Kepsek/WA | Otorisasi melebar | Kepsek/WA out-of-scope default; butuh persetujuan eksplisit |
| Kamera HP/HTTPS | Foto gagal diam-diam | Pola galeri fallback + catatan batas PHP seperti form siswa |

### 18.2 Dependensi

- `classes/majors/students/teachers/users_baru/duty_schedules/report_categories` harus stabil; perubahan enum/hapus tabel lama di luar PRD ini.
- `GuruDutyService`, `SecureAttachment`, `ReportIdempotency`, `SingleSession`, guard test wajib dipakai ulang polanya (bukan diduplikasi).
- Aset `si-lpiket.css` + logo dipakai untuk konsistensi; tidak ada desain baru di luar pola `app-shell`.

### 18.3 Keputusan yang sudah disepakati (fakta tahap PRD)

- Tidak ada keputusan implementasi yang disepakati pada tahap ini. Tahap ini hanya audit + PRD.
- Batasan yang dipatuhi: tanpa ubah kode/DB/migrasi/paket; tanpa perintah destruktif; tanpa menandai fitur selesai; perubahan Git tetap dipertahankan (working tree clean saat audit).
- Ketidakcocokan nama migrasi (`024938...` di tab vs 9 file aktual) dicatat sebagai temuan, bukan diperbaiki pada tahap ini.

### 18.4 Pertanyaan yang masih terbuka (wajib dijawab sebelum implementasi)

1. Pilih Opsi A atau B untuk DB (bagian 15)? Bila A: setujui nama tabel/kolom dan penanganan notifikasi/lampiran.
2. Siapa pelapor sah (Opsi A/B/C di 7.2.5)? Apakah `km/wkm` + `temporary_reporters` dipakai?
3. Apakah kategori laporan dipakai untuk piket kelas? Bila ya, apakah kategori khusus atau kategori masalah yang sama?
4. Apakah laporan insidental (di luar jadwal) diizinkan? Bila ya, aturan tanggal + anti-gandanya?
5. Apakah persetujuan Guru final atau perlu teruskan ke Kepsek? Bila teruskan, status + otorisasi Kepsek?
6. Jendela perbaikan setelah dikembalikan (mis. hari yang sama / H+1 / tanpa batas)?
7. Apakah beberapa kelas boleh piket pada hari yang sama? Apakah satu kelas boleh dua slot sehari?
8. Apakah Minggu/libur nasional selalu libur? Bagaimana hari libur di luar Minggu?
9. Struktur notifikasi untuk entitas baru (kolom baru vs tabel baru)?
10. Direktori lampiran piket kelas (`reports/` vs subdirektori vs direktori baru + perubahan `SecureAttachment`)?

## 19. Tahapan Implementasi dan Rencana Verifikasi Akhir

> **Status: rencana usulan. Jangan dilaksanakan sebelum persetujuan eksplisit pengguna.**

### 19.1 Tahapan usulan

1. **Persetujuan PRD:** jawab 10 pertanyaan terbuka (18.4); pilih Opsi DB; kunci status (9.2) dan pelapor sah (7.2.5). Tanpa ini, dilarang koding.
2. **Desain teknis:** ERD + daftar route/controller/view/test baru; konfirmasi tidak menyentuh file laporan masalah (daftar file yang boleh disentuh vs dilarang).
3. **Migrasi non-destruktif:** buat migrasi Opsi A (idempoten, FK eksplisit); review `--pretend`; uji di DB terisolasi; tidak ke produksi.
4. **Backend:** model + middleware/otorisasi objek + controller + histori + notifikasi + anti-ganda + timezone Jakarta; semua transaksi + validasi server.
5. **Frontend:** 5 halaman (12.2) mengikuti `app-shell` + responsif seluler + kamera/galeri + badge status baru.
6. **Pengujian:** tulis Feature tests baru (17.1) + jalankan regresi penuh (17.2); perbaiki hingga hijau; verifikasi guard DB.
7. **UAT + verifikasi akhir:** skenario AC-PK-01–12 di lingkungan staging dengan data `classes/majors/students/teachers/duty_schedules` nyata (bukan produksi bila berisiko); cek tengah malam + Minggu + paralel ganda.
8. **Serah terima:** catat keputusan final di PRD (naikkan versi ke 1.1.0-disetujui), sertakan daftar migrasi + cara rollback aman (down non-destruktif).

### 19.2 Rencana verifikasi akhir (checklist)

- [ ] File PRD final berversi + jawaban 18.4 tertulis.
- [ ] Migrasi baru idempoten + FK benar + tanpa ubah tabel lama (bila Opsi A).
- [ ] AC-PK-01–12 lolos (bukti test/UAT, bukan klaim).
- [ ] Regresi laporan masalah hijau; working tree hanya berisi perubahan yang disetujui.
- [ ] Tidak ada akses ke laporan/lampiran lintas hak (uji IDOR/traversal/MIME).
- [ ] Tanggal Jakarta + anti-ganda `(class_id, tanggal)` terbukti pada batas tengah malam + paralel.
- [ ] Persetujuan eksplisit pengguna sebelum merge/deploy.

---

### Lampiran Audit — Bukti File yang Diverifikasi (fakta)

- `routes/web.php` (119 baris), `bootstrap/app.php` (alias `role`, middleware web), `routes/console.php` (`notifications:cleanup` harian).
- `config/app.php:68` (timezone), `.env.example:6` (`APP_TIMEZONE`), `composer.json` (Laravel ^13.17, PHP ^8.3), `phpunit.xml` (sqlite-memory testing).
- `app/Services/GuruDutyService.php` (TIMEZONE, todayDay, isOnDutyDay, canClaim, isContinuable, canTakeover).
- `app/Http/Controllers/Admin/DutyScheduleController.php` (DAYS senin–sabtu, CRUD, anti-duplikat teacher+day).
- `app/Http/Controllers/Siswa/ReportController.php` (261 baris; validasi foto, idempotency, transaksi, notifikasi guru).
- `app/Http/Controllers/Guru/ReportController.php` (396 baris; claim/takeover/complete/forward atomik).
- `app/Http/Controllers/KepalaSekolah/ReportController.php` (`authorizeReport`, complete/return).
- `app/Http/Controllers/AuthController.php`, `EnsureRole.php`, `UserBaru.php`, `Teacher.php` (`resolveForUser`), `Student.php`, `SchoolClass.php`, `Major.php`, `Report.php`, `DutySchedule.php`, `ClassRepresentative.php`, `TemporaryReporter.php`, `Setting.php`.
- `app/Support/SecureAttachment.php`, `ReportIdempotency.php`; `app/Http/Controllers/NotificationController.php`, `ProfileController.php`.
- Migrasi: base `032134`, FK `032500`, repoint `033437`, align `034754`, settings `115116`, NIP unique `000001`; cache/jobs/users bawaan.
- Views: `admin.duty_schedules.{index,create,edit}`, `siswa.reports.{index,create,show}`, `guru.reports.{index,show}`, `kepala-sekolah.reports.{index,show}`, layout per role, `auth.login`; aset `public/css/si-lpiket.css`, `public/images/Logo-si-lpiket.jpg`.
- Tests: 49 Feature + 1 Unit via glob; `TestCase.php` + `TestDatabaseGuard.php` (tolak `si_l_piket`).
- Perintah audit hanya baca (`Get-Content`, `Select-String`, `Get-ChildItem`, `git status/log`, `Test-Path`); tanpa tulis kode/DB/migrasi/paket; tanpa perintah destruktif.

*Akhir PRD draft 1.0.0 — menunggu persetujuan eksplisit sebelum implementasi.*
