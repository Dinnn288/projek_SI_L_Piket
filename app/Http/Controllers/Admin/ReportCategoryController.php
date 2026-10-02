<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = ReportCategory::withCount('reports');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('priority', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $categories = $query->orderBy('name')->paginate(10)->withQueryString();

        return view('admin.report_categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.report_categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200', 'unique:report_categories,name'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'priority' => ['required', 'in:rendah,sedang,tinggi'],
        ]);

        ReportCategory::create($data);

        return redirect()->route('admin.report-categories.index')
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(ReportCategory $reportCategory): View
    {
        return view('admin.report_categories.edit', ['category' => $reportCategory]);
    }

    public function update(Request $request, ReportCategory $reportCategory): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200', 'unique:report_categories,name,'.$reportCategory->id],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:aktif,nonaktif'],
            'priority' => ['required', 'in:rendah,sedang,tinggi'],
        ]);

        $reportCategory->update($data);

        return redirect()->route('admin.report-categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(ReportCategory $reportCategory): RedirectResponse
    {
        // Laporan apa pun (termasuk selesai) tetap dilindungi FK RESTRICT.
        if ($reportCategory->reports()->exists()) {
            return back()->with('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh laporan.');
        }

        $reportCategory->delete();

        return redirect()->route('admin.report-categories.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }
}
