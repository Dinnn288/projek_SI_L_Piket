<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Major;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolClassController extends Controller
{
    public function index(Request $request): View
    {
        $query = SchoolClass::with('major')->withCount('students');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('major', function ($mq) use ($search) {
                        $mq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('major_id') && Major::whereKey($request->input('major_id'))->exists()) {
            $query->where('major_id', $request->input('major_id'));
        }

        if (in_array($request->input('status'), ['aktif', 'nonaktif'], true)) {
            $query->where('status', $request->input('status'));
        }

        $classes = $query->orderBy('name')->paginate(10)->withQueryString();
        $majors = Major::orderBy('name')->get(['id', 'name']);

        return view('admin.classes.index', compact('classes', 'majors'));
    }

    public function create(): View
    {
        $majors = Major::orderBy('name')->get();

        return view('admin.classes.create', compact('majors'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'major_id' => ['required', 'exists:majors,id'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        SchoolClass::create($data);

        return redirect()->route('admin.classes.index')
            ->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function edit(SchoolClass $class): View
    {
        $majors = Major::orderBy('name')->get();

        return view('admin.classes.edit', [
            'schoolClass' => $class,
            'majors' => $majors,
        ]);
    }

    public function update(Request $request, SchoolClass $class): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'major_id' => ['required', 'exists:majors,id'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        $class->update($data);

        return redirect()->route('admin.classes.index')
            ->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        $hasRelation = $class->students()->exists()
            || $class->classRepresentatives()->exists()
            || $class->temporaryReporters()->exists()
            || $class->reports()->exists();

        if ($hasRelation) {
            return back()->with('error', 'Kelas tidak dapat dihapus karena masih memiliki data terkait dengan sistem.');
        }

        $class->delete();

        return redirect()->route('admin.classes.index')
            ->with('success', 'Kelas berhasil dihapus.');
    }
}
