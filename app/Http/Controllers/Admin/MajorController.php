<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Major;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MajorController extends Controller
{
    public function index(Request $request): View
    {
        $query = Major::withCount('classes');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if (in_array($request->input('status'), ['aktif', 'nonaktif'], true)) {
            $query->where('status', $request->input('status'));
        }

        $majors = $query->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.majors.index', compact('majors'));
    }

    public function create(): View
    {
        return view('admin.majors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200', 'unique:majors,name'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        Major::create($data);

        return redirect()->route('admin.majors.index')
            ->with('success', 'Jurusan berhasil ditambahkan.');
    }

    public function edit(Major $major): View
    {
        return view('admin.majors.edit', compact('major'));
    }

    public function update(Request $request, Major $major): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200', 'unique:majors,name,'.$major->id],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        $major->update($data);

        return redirect()->route('admin.majors.index')
            ->with('success', 'Jurusan berhasil diperbarui.');
    }

    public function destroy(Major $major): RedirectResponse
    {
        if ($major->classes()->exists()) {
            return back()->with('error', 'Jurusan tidak dapat dihapus karena masih digunakan oleh kelas.');
        }

        $major->delete();

        return redirect()->route('admin.majors.index')
            ->with('success', 'Jurusan berhasil dihapus.');
    }
}
