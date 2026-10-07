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
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_baru,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:admin,siswa,guru,kepala_sekolah'],
            'status' => ['required', 'in:aktif,nonaktif'],
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

            static::ensureProfileFor($user);
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * Buat record profil pendamping sesuai role secara idempoten.
     *
     * - guru: teachers (nip diisi dari username yang unik per akun).
     * - siswa: students membutuhkan nis unik + class_id yang tidak tersedia
     *   di form Users; gunakan nis = username bila belum dipakai dan
     *   class pertama yang tersedia. Jika tidak ada kelas, lempar error
     *   validasi agar Admin melengkapi via Data Siswa (tanpa data palsu).
     */
    public static function ensureProfileFor(UserBaru $user): void
    {
        if ($user->role === 'guru') {
            Teacher::firstOrCreate(
                ['user_id' => $user->id],
                ['nip' => $user->username, 'phone' => null]
            );

            return;
        }

        if ($user->role === 'siswa') {
            if ($user->student()->exists()) {
                return;
            }

            $classId = SchoolClass::orderBy('id')->value('id');

            if (! $classId) {
                throw ValidationException::withMessages([
                    'role' => 'Belum ada data kelas. Buat kelas terlebih dahulu melalui Admin → Kelas, lalu lengkapi data siswa via Admin → Siswa.',
                ]);
            }

            Student::firstOrCreate(
                ['user_id' => $user->id],
                ['nis' => $user->username, 'class_id' => $classId, 'phone' => null]
            );
        }
    }

    public function edit(UserBaru $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, UserBaru $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_baru,username,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:admin,siswa,guru,kepala_sekolah'],
            'status' => ['required', 'in:aktif,nonaktif'],
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

            static::ensureProfileFor($user->fresh());
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
