<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\DutySchedule;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportCategory;
use App\Models\Student;
use App\Services\GuruDutyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    private function currentStudent(Request $request): Student
    {
        $student = $request->user()->student()->with('schoolClass')->first();

        if (! $student || ! $student->class_id) {
            abort(403, 'Data siswa tidak ditemukan.');
        }

        return $student;
    }

    public function index(Request $request): View
    {
        $student = $this->currentStudent($request);

        $reports = Report::where('student_id', $student->id)
            ->with(['category', 'schoolClass'])
            ->withCount('attachments')
            ->latest()
            ->get();

        return view('siswa.reports.index', compact('student', 'reports'));
    }

    public function create(Request $request): View
    {
        $student = $this->currentStudent($request);

        $categories = ReportCategory::where('status', 'aktif')->orderBy('name')->get();

        return view('siswa.reports.create', compact('student', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $this->currentStudent($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'exists:report_categories,id'],
            'location' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $category = ReportCategory::where('status', 'aktif')->find($validated['category_id']);

        if (! $category) {
            return back()->withErrors(['category_id' => 'Kategori yang dipilih tidak valid.'])->withInput();
        }

        $storedPaths = [];
        DB::beginTransaction();

        try {
            $report = Report::create([
                'student_id' => $student->id,
                'class_id' => $student->class_id,
                'category_id' => $category->id,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'location' => $validated['location'],
                'priority' => $category->priority,
                'status' => 'menunggu',
            ]);

            /** @var \Illuminate\Http\UploadedFile $photo */
            foreach ($request->file('photos') as $photo) {
                // Disk private: storage/app/reports — tidak reachable via
                // public/storage symlink; akses hanya via endpoint terproteksi.
                $path = $photo->store('reports', 'local');
                $storedPaths[] = $path;

                $report->attachments()->create([
                    'file_patch' => $path,
                    'original_name' => $photo->getClientOriginalName(),
                    'file_size' => $photo->getSize(),
                    'mime_type' => $photo->getMimeType() ?? $photo->getClientMimeType(),
                ]);
            }

            // Notifikasi laporan baru ke Guru Piket hari ini (server-side,
            // di dalam transaction yang sama sehingga ikut rollback bila gagal).
            $today = GuruDutyService::todayDay();

            if ($today !== null) {
                $recipientIds = DutySchedule::where('day', $today)
                    ->with('teacher')
                    ->get()
                    ->map(fn ($schedule) => (int) $schedule->teacher?->user_id)
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($recipientIds as $userId) {
                    $exists = Notification::where('user_id', $userId)
                        ->where('report_id', $report->id)
                        ->where('title', 'Laporan Baru')
                        ->exists();

                    if (! $exists) {
                        Notification::create([
                            'user_id' => $userId,
                            'report_id' => $report->id,
                            'title' => 'Laporan Baru',
                            'message' => 'Ada laporan baru yang perlu diperiksa.',
                            'is_read' => false,
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            report($e);

            return back()->withErrors(['photos' => 'Gagal menyimpan laporan. Silakan coba lagi.'])->withInput();
        }

        return redirect()->route('siswa.reports.show', $report)
            ->with('success', 'Laporan berhasil dikirim.');
    }

    public function show(Request $request, Report $report): View
    {
        $student = $this->currentStudent($request);

        if ((int) $report->student_id !== (int) $student->id) {
            abort(404);
        }

        $report->load(['category', 'schoolClass', 'attachments']);

        return view('siswa.reports.show', compact('student', 'report'));
    }

    public function attachment(Request $request, Report $report, ReportAttachment $attachment): BinaryFileResponse
    {
        $student = $this->currentStudent($request);

        if ((int) $report->student_id !== (int) $student->id) {
            abort(404);
        }

        if ((int) $attachment->report_id !== (int) $report->id) {
            abort(404);
        }

        $absolutePath = Storage::disk('local')->path($attachment->file_patch);

        if (! is_file($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath);
    }
}
