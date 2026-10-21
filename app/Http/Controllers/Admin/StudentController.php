<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\UserBaru;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Student::with(['userBaru', 'schoolClass.major']);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nis', 'like', "%{$search}%")
                    ->orWhereHas('userBaru', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    })
                    ->orWhereHas('schoolClass', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('class_id') && SchoolClass::whereKey($request->input('class_id'))->exists()) {
            $query->where('class_id', $request->input('class_id'));
        }

        if (in_array($request->input('status'), ['aktif', 'nonaktif'], true)) {
            $query->whereHas('userBaru', function ($uq) use ($request) {
                $uq->where('status', $request->input('status'));
            });
        }

        $students = $query->orderBy('nis')->paginate(10)->withQueryString();
        $classes = SchoolClass::orderBy('name')->get(['id', 'name']);

        return view('admin.students.index', compact('students', 'classes'));
    }

    public function create(): View
    {
        $classes = SchoolClass::with('major')->orderBy('name')->get();

        return view('admin.students.create', compact('classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_baru,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'nis' => ['required', 'string', 'max:50', 'unique:students,nis'],
            'class_id' => ['required', 'exists:classes,id'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($data) {
            $user = UserBaru::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'role' => 'siswa',
                'status' => $data['status'],
            ]);

            Student::create([
                'user_id' => $user->id,
                'nis' => $data['nis'],
                'class_id' => $data['class_id'],
                'phone' => $data['phone'] ?? null,
            ]);
        });

        return redirect()->route('admin.students.index')
            ->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function edit(Student $student): View
    {
        $student->load(['userBaru', 'schoolClass']);
        $classes = SchoolClass::with('major')->orderBy('name')->get();

        return view('admin.students.edit', compact('student', 'classes'));
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $user = $student->userBaru;

        if (! $user) {
            return back()->with('error', 'Data akun siswa tidak ditemukan.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users_baru,username,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'nis' => ['required', 'string', 'max:50', 'unique:students,nis,'.$student->id],
            'class_id' => ['required', 'exists:classes,id'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $oldClassId = $student->class_id;
        $newClassId = (int) $data['class_id'];

        DB::transaction(function () use ($student, $user, $data, $oldClassId, $newClassId) {
            $user->name = $data['name'];
            $user->username = $data['username'];
            $user->status = $data['status'];

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            $student->nis = $data['nis'];
            $student->class_id = $data['class_id'];
            $student->phone = $data['phone'] ?? null;
            $student->save();

            // Hapus jadwal piket siswa untuk kelas lama jika kelas berubah
            if ($oldClassId !== $newClassId) {
                \App\Models\ClassDutyStudentSchedule::where('student_id', $student->id)
                    ->where('class_id', $oldClassId)
                    ->delete();
            }
        });

        return redirect()->route('admin.students.index')
            ->with('success', 'Siswa berhasil diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $hasRelation = $student->reports()->exists()
            || $student->classRepresentatives()->exists()
            || $student->temporaryReporters()->exists()
            || $student->classDutyReports()->exists();

        if ($hasRelation) {
            return back()->with('error', 'Siswa tidak dapat dihapus karena masih memiliki data laporan/relasi sistem.');
        }

        DB::transaction(function () use ($student) {
            $userId = $student->user_id;
            $student->delete();

            $stillUsed = Student::where('user_id', $userId)->exists();

            if (! $stillUsed) {
                UserBaru::where('id', $userId)
                    ->where('role', 'siswa')
                    ->delete();
            }
        });

        return redirect()->route('admin.students.index')
            ->with('success', 'Siswa berhasil dihapus.');
    }
}
