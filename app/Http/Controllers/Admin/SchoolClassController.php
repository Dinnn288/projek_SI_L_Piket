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
    public function index(): View
    {
        $classes = SchoolClass::with('major')->withCount('students')->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.classes.index', compact('classes'));
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
