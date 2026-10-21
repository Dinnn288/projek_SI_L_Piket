<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Rules\IndonesianWhatsappNumber;
use App\Support\HandlesNipUniqueViolation;
use App\Support\WaCredentialDispatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    use HandlesNipUniqueViolation;

    public function index(Request $request): View
    {
        $query = UserBaru::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if (in_array($request->input('role'), ['admin', 'siswa', 'guru', 'kepala_sekolah'], true)) {
            $query->where('role', $request->input('role'));
        }

        if (in_array($request->input('status'), ['aktif', 'nonaktif'], true)) {
            $query->where('status', $request->input('status'));
        }

        $users = $query->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $classes = SchoolClass::with('major')->orderBy('name')->get();

        return view('admin.users.create', compact('classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Aturan identitas mengikuti form Siswa/Guru: siswa wajib NIS unik
        // + kelas, guru wajib NIP unik. Validasi kondisional per role agar
        // akun Users tidak melewati aturan pembuatan siswa/guru.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_baru,username'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,siswa,guru,kepala_sekolah'],
            'status' => ['required', 'in:aktif,nonaktif'],
            // Nomor WA penerima: opsional, hanya relevan untuk role yang
            // memenuhi syarat kirim (siswa/guru/kepala_sekolah). Bila diisi,
            // disimpan ke kolom phone profil yang memang sudah ada
            // (students/teachers) — tanpa perubahan skema.
            'wa_number' => ['nullable', 'string', 'max:25', new IndonesianWhatsappNumber()],
            'nis' => ['required_if:role,siswa', 'nullable', 'string', 'max:50', 'unique:students,nis'],
            'class_id' => ['required_if:role,siswa', 'nullable', 'exists:classes,id'],
            'nip' => [
                'required_if:role,guru', 'nullable', 'string', 'max:50',
                Rule::unique('teachers', 'nip'),
            ],
        ]);

        // Normalisasi nomor + simpan ke profil bila role memakai profil
        // berkolom phone (siswa/guru). Kepala sekolah tidak punya profil
        // phone sehingga nomor hanya dipakai sekali untuk pengiriman ini
        // (tidak disimpan permanen) — lihat pending di bawah.
        $waTarget = IndonesianWhatsappNumber::normalize($data['wa_number'] ?? null);

        if (($data['wa_number'] ?? null) !== null && $waTarget === null) {
            throw ValidationException::withMessages([
                'wa_number' => 'Nomor WhatsApp tidak valid. Gunakan format 08xxxxxxxxxx.',
            ]);
        }

        if ($waTarget !== null && ! in_array($data['role'], ['siswa', 'guru', 'kepala_sekolah'], true)) {
            throw ValidationException::withMessages([
                'wa_number' => 'Pengiriman WhatsApp hanya untuk role siswa, guru, atau kepala sekolah.',
            ]);
        }

        $user = null;

        // B2.2-H2: pelanggaran UNIQUE NIP (race) menjadi error validasi;
        // exception dari ensureProfileFor ikut tertangani di batas ini.
        $this->transactionalNipSafe(function () use ($data, $waTarget, &$user) {
            $user = UserBaru::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status' => $data['status'],
            ]);

            static::ensureProfileFor($user, $data, $waTarget);
        });

        // Bila nomor WA diisi untuk role yang memenuhi syarat: siapkan
        // rahasia sekali-pakai (password terenkripsi, TTL singkat) lalu
        // arahkan ke halaman konfirmasi. Tanpa nomor -> alur lama.
        if ($waTarget !== null && $user !== null
            && in_array($user->role, ['siswa', 'guru', 'kepala_sekolah'], true)) {
            $pending = WaCredentialDispatch::createPending(
                (int) $request->user()->id, (int) $user->id, $waTarget
            );

            if ($pending !== null) {
                return redirect()->route('admin.users.wa.show', $pending['id'])
                    ->with('success', 'Akun berhasil dibuat. Periksa kembali data sebelum mengirim WhatsApp.');
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * Buat record profil pendamping sesuai role secara idempoten.
     *
     * - guru: teachers memakai NIP dari form (wajib + unik, sama seperti
     *   form Guru). Nilai username TIDAK dipakai sebagai NIP agar tidak
     *   tertukar dan tidak melanggar unique bila username sama dengan NIP
     *   akun lain.
     * - siswa: students memakai NIS + class_id dari form (sama seperti
     *   form Siswa). Jika tidak ada kelas, lempar error validasi agar
     *   Admin melengkapi via Data Siswa (tanpa data palsu).
     *
     * @param  array<string, mixed>  $data  Data tervalidasi dari form.
     */
    public static function ensureProfileFor(UserBaru $user, array $data = [], ?string $waTarget = null): void
    {
        if ($user->role === 'guru') {
            $teacher = Teacher::firstOrCreate(
                ['user_id' => $user->id],
                ['nip' => (string) ($data['nip'] ?? $user->username), 'phone' => null]
            );

            if ($waTarget !== null && $teacher->phone !== $waTarget) {
                $teacher->phone = $waTarget;
                $teacher->save();
            }

            return;
        }

        if ($user->role === 'siswa') {
            if ($user->student()->exists()) {
                if ($waTarget !== null) {
                    $user->student()->update(['phone' => $waTarget]);
                }

                return;
            }

            $classId = $data['class_id'] ?? SchoolClass::orderBy('id')->value('id');

            if (! $classId) {
                throw ValidationException::withMessages([
                    'role' => 'Belum ada data kelas. Buat kelas terlebih dahulu melalui Admin → Kelas, lalu lengkapi data siswa via Admin → Siswa.',
                ]);
            }

            Student::firstOrCreate(
                ['user_id' => $user->id],
                ['nis' => (string) ($data['nis'] ?? $user->username), 'class_id' => $classId, 'phone' => $waTarget]
            );
        }
    }

    public function edit(UserBaru $user): View
    {
        $user->load(['student', 'teacher']);
        $classes = SchoolClass::with('major')->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'classes'));
    }

    public function update(Request $request, UserBaru $user): RedirectResponse
    {
        // Validasi identitas mengikuti form tambah: siswa wajib NIS unik
        // (kecualikan profil sendiri) + kelas valid; guru wajib NIP unik
        // (kecualikan profil sendiri). Unique memakai kolom id profil
        // (students.id / teachers.id), bukan user_id.
        $studentId = $user->student()->value('id');
        $teacherId = $user->teacher()->value('id');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_baru,username,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:admin,siswa,guru,kepala_sekolah'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'nis' => [
                'required_if:role,siswa', 'nullable', 'string', 'max:50',
                $studentId ? Rule::unique('students', 'nis')->ignore($studentId) : 'unique:students,nis',
            ],
            'class_id' => ['required_if:role,siswa', 'nullable', 'exists:classes,id'],
            'nip' => [
                'required_if:role,guru', 'nullable', 'string', 'max:50',
                $teacherId ? Rule::unique('teachers', 'nip')->ignore($teacherId) : Rule::unique('teachers', 'nip'),
            ],
        ]);

        $oldRole = $user->role;

        $this->transactionalNipSafe(function () use ($user, $data, $oldRole) {
            // Cegah perubahan role yang meninggalkan profil yatim ber-relasi:
            // hanya boleh bila profil lama tidak memiliki data terkait.
            if ($oldRole !== $data['role']) {
                $blocking = match ($oldRole) {
                    'guru' => ($teacher = $user->teacher()->first())
                        && ($teacher->dutySchedules()->exists()
                            || $teacher->assignedReports()->exists()
                            || $user->reportHistories()->exists()),
                    'siswa' => ($student = $user->student()->first())
                        && ($student->reports()->exists()
                            || $student->classRepresentatives()->exists()
                            || $student->temporaryReporters()->exists()),
                    default => false,
                };

                if ($blocking) {
                    throw ValidationException::withMessages([
                        'role' => 'Role tidak dapat diubah karena akun masih memiliki data terkait.',
                    ]);
                }

                // Pindahkan profil: hapus profil lama yang tidak berelasi,
                // profil baru dibuat setelah save di bawah.
                if ($oldRole === 'guru') {
                    $user->teacher()->delete();
                }

                if ($oldRole === 'siswa') {
                    $user->student()->delete();
                }
            }

            $user->name = $data['name'];
            $user->username = $data['username'];
            $user->role = $data['role'];
            $user->status = $data['status'];

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            $fresh = $user->fresh();

            // Sinkronisasi profil saat role TETAP (update NIS/NIP/kelas)
            // maupun saat role BERGANTI (profil baru dari data form, profil
            // lama tak-berelasi sudah dihapus di atas). Tanpa fallback
            // username/kelas-pertama: data wajib dari form yang tervalidasi.
            if ($fresh->role === 'siswa') {
                $student = $fresh->student()->first();

                if ($student) {
                    $student->nis = (string) $data['nis'];
                    $student->class_id = $data['class_id'];
                    $student->save();
                } else {
                    Student::create([
                        'user_id' => $fresh->id,
                        'nis' => (string) $data['nis'],
                        'class_id' => $data['class_id'],
                        'phone' => null,
                    ]);
                }
            } elseif ($fresh->role === 'guru') {
                $teacher = $fresh->teacher()->first();

                if ($teacher) {
                    $teacher->nip = (string) $data['nip'];
                    $teacher->save();
                } else {
                    Teacher::create([
                        'user_id' => $fresh->id,
                        'nip' => (string) $data['nip'],
                        'phone' => null,
                    ]);
                }
            }
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(UserBaru $user): RedirectResponse
    {
        // Kebijakan fitur: akun role admin TIDAK boleh dihapus dalam kondisi
        // apa pun melalui fitur ini (berlaku untuk admin lain maupun akun
        // sendiri). Pemeriksaan memakai role dari database, bukan input
        // browser. Akun sendiri non-admin tetap ditolak di bawah.
        if ($user->role === 'admin') {
            return back()->with('error', 'Akun dengan role Admin tidak dapat dihapus melalui fitur ini.');
        }

        // Pengecualian keamanan: akun sendiri tidak boleh dihapus.
        if ((int) Auth::id() === (int) $user->id) {
            return back()->with('error', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }

        try {
            DB::transaction(function () use ($user) {
                $teacher = $user->role === 'guru' ? $user->teacher()->first() : null;
                $student = $user->role === 'siswa' ? $user->student()->first() : null;

                // Relasi pemblokir (RESTRICT + NOT NULL, tanpa ubah skema):
                // laporan siswa, perwakilan/temporary, histori tindakan, dan
                // aktivasi yang dilakukan akun. Ini dipertahankan — penghapusan
                // ditolak dengan pesan jelas, bukan histori yang dihapus.
                $blockers = [];

                if ($student && $student->reports()->exists()) {
                    $blockers[] = 'laporan siswa';
                }

                if ($student && $student->classDutyReports()->exists()) {
                    $blockers[] = 'laporan piket kelas';
                }

                if (\App\Models\ClassDutyReportHistory::where('user_id', $user->id)->exists()) {
                    $blockers[] = 'riwayat piket kelas';
                }

                if ($student && ($student->classRepresentatives()->exists() || $student->temporaryReporters()->exists())) {
                    $blockers[] = 'data perwakilan/temporary';
                }

                if ($user->reportHistories()->exists()) {
                    $blockers[] = 'riwayat penanganan';
                }

                if ($user->temporaryReporters()->exists()) {
                    $blockers[] = 'aktivasi pengganti';
                }

                if ($blockers !== []) {
                    throw ValidationException::withMessages([
                        'user' => 'Akun tidak dapat dihapus karena masih memiliki '.implode(', ', $blockers).'. Laporan dan histori tetap dipertahankan.',
                    ]);
                }

                // Jadwal piket milik guru: aman dihapus (penugasan, bukan
                // histori; histori penanganan ada di report_histories yang
                // justru memblokir di atas bila ada). Hanya milik guru ini.
                if ($teacher) {
                    // Laporan yang sedang ditugaskan ke guru ini dilepas
                    // (SET NULL, sama seperti perilaku FK) tanpa mengubah
                    // status/isi laporan.
                    Report::where('assigned_teacher_id', $teacher->id)->update(['assigned_teacher_id' => null]);
                    $teacher->dutySchedules()->delete();
                }

                // Notifikasi milik akun: data personal, ikut terhapus agar
                // tidak yatim (laporan yang dirujuk notifikasi tetap ada).
                $user->notifications()->delete();

                // Hapus akun; profil ikut terhapus via CASCADE FK
                // (students/teachers.user_id). Status aktif/nonaktif bukan
                // alasan penolakan — semua role dapat dihapus.
                $user->delete();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Akun gagal dihapus karena kendala data terkait. Silakan coba lagi.');
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil dihapus.');
    }
}
