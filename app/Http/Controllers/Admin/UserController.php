<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:admin,siswa,guru,kepala_sekolah'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'nis' => ['required_if:role,siswa', 'nullable', 'string', 'max:50', 'unique:students,nis'],
            'class_id' => ['required_if:role,siswa', 'nullable', 'exists:classes,id'],
            'nip' => [
                'required_if:role,guru', 'nullable', 'string', 'max:50',
                Rule::unique('teachers', 'nip'),
            ],
        ]);

        $user = null;

        DB::transaction(function () use ($data, &$user) {
            $user = UserBaru::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status' => $data['status'],
            ]);

            static::ensureProfileFor($user, $data);
        });

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
    public static function ensureProfileFor(UserBaru $user, array $data = []): void
    {
        if ($user->role === 'guru') {
            Teacher::firstOrCreate(
                ['user_id' => $user->id],
                ['nip' => (string) ($data['nip'] ?? $user->username), 'phone' => null]
            );

            return;
        }

        if ($user->role === 'siswa') {
            if ($user->student()->exists()) {
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
                ['nis' => (string) ($data['nis'] ?? $user->username), 'class_id' => $classId, 'phone' => null]
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

        DB::transaction(function () use ($user, $data, $oldRole) {
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
        if ((int) Auth::id() === (int) $user->id) {
            return back()->with('error', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }

        DB::transaction(function () use ($user) {
            // Hapus profil pendamping yang tidak berelasi agar tidak yatim.
            // Profil berelasi (ada laporan/jadwal/history) tetap memblokir
            // penghapusan via pengecekan di bawah.
            if ($user->role === 'guru' && ($teacher = $user->teacher()->first())) {
                if (! $teacher->dutySchedules()->exists()
                    && ! $teacher->assignedReports()->exists()
                    && ! $user->reportHistories()->exists()) {
                    $teacher->delete();
                }
            }

            if ($user->role === 'siswa' && ($student = $user->student()->first())) {
                if (! $student->reports()->exists()
                    && ! $student->classRepresentatives()->exists()
                    && ! $student->temporaryReporters()->exists()) {
                    $student->delete();
                }
            }

            $hasRelation = $user->student()->exists()
                || $user->teacher()->exists()
                || $user->temporaryReporters()->exists()
                || $user->reportHistories()->exists()
                || $user->notifications()->exists();

            if ($hasRelation) {
                throw ValidationException::withMessages([
                    'user' => 'Akun tidak dapat dihapus karena masih digunakan oleh data terkait.',
                ]);
            }

            $user->delete();
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil dihapus.');
    }
}
