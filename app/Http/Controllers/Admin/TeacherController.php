<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Support\HandlesNipUniqueViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeacherController extends Controller
{
    use HandlesNipUniqueViolation;

    public function index(Request $request): View
    {
        $query = Teacher::with(['userBaru']);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nip', 'like', "%{$search}%")
                    ->orWhereHas('userBaru', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array($request->input('status'), ['aktif', 'nonaktif'], true)) {
            $query->whereHas('userBaru', function ($uq) use ($request) {
                $uq->where('status', $request->input('status'));
            });
        }

        $teachers = $query->orderBy('nip')->paginate(10)->withQueryString();

        return view('admin.teachers.index', compact('teachers'));
    }

    public function create(): View
    {
        return view('admin.teachers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_baru,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'nip' => ['required', 'string', 'max:50', Rule::unique('teachers', 'nip')],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        // B2.2-H2: race NIP (lolos validasi bersamaan) ditangkap sebagai
        // error validasi via UNIQUE DB; rollback otomatis oleh transaction.
        $this->transactionalNipSafe(function () use ($data) {
            $user = UserBaru::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'role' => 'guru',
                'status' => $data['status'],
            ]);

            Teacher::create([
                'user_id' => $user->id,
                'nip' => $data['nip'],
                'phone' => $data['phone'] ?? null,
            ]);
        });

        return redirect()->route('admin.teachers.index')
            ->with('success', 'Guru berhasil ditambahkan.');
    }

    public function edit(Teacher $teacher): View
    {
        $teacher->load('userBaru');

        return view('admin.teachers.edit', compact('teacher'));
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $user = $teacher->userBaru;

        if (! $user) {
            return back()->with('error', 'Data akun guru tidak ditemukan.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_baru,username,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'nip' => ['required', 'string', 'max:50', Rule::unique('teachers', 'nip')->ignore($teacher->id)],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $this->transactionalNipSafe(function () use ($teacher, $user, $data) {
            $user->name = $data['name'];
            $user->username = $data['username'];
            $user->status = $data['status'];

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            $teacher->nip = $data['nip'];
            $teacher->phone = $data['phone'] ?? null;
            $teacher->save();
        });

        return redirect()->route('admin.teachers.index')
            ->with('success', 'Guru berhasil diperbarui.');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        // Jadwal aktif: FK RESTRICT — tolak agar admin kelola via menu Jadwal.
        if ($teacher->dutySchedules()->exists()) {
            return back()->with('error', 'Guru tidak dapat dihapus karena masih memiliki jadwal piket.');
        }

        // History sebagai actor: FK RESTRICT — tolak agar histori tidak invalid.
        $user = $teacher->userBaru;

        if ($user && $user->reportHistories()->exists()) {
            return back()->with('error', 'Guru tidak dapat dihapus karena masih memiliki riwayat penanganan laporan.');
        }

        // Laporan aktif: business rule — jangan hilangkan penanggung jawab
        // yang masih menangani/diteruskan.
        $hasActiveReports = $teacher->assignedReports()
            ->whereIn('status', ['menunggu', 'dalam_penanganan', 'diteruskan'])
            ->exists();

        if ($hasActiveReports) {
            return back()->with('error', 'Guru tidak dapat dihapus karena masih menangani laporan aktif.');
        }

        DB::transaction(function () use ($teacher) {
            $userId = $teacher->user_id;
            // Laporan selesai → FK SET NULL: biarkan DB men-set NULL.
            $teacher->delete();

            $stillUsed = Teacher::where('user_id', $userId)->exists();

            if (! $stillUsed) {
                UserBaru::where('id', $userId)
                    ->where('role', 'guru')
                    ->delete();
            }
        });

        return redirect()->route('admin.teachers.index')
            ->with('success', 'Guru berhasil dihapus.');
    }
}
