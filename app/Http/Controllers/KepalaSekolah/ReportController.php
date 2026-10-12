<?php

namespace App\Http\Controllers\KepalaSekolah;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportHistory;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        $reports = Report::where('status', 'diteruskan')
            ->with(['student.userBaru', 'schoolClass', 'category', 'assignedTeacher.userBaru', 'attachments'])
            ->with(['histories' => fn ($q) => $q->where('action', 'forwarded')->latest('id')->limit(1)])
            ->latest()
            ->paginate(10)->withQueryString();

        return view('kepala-sekolah.reports.index', compact('reports'));
    }

    /**
     * Otorisasi objek kepsek (A1): izinkan bila status saat ini
     * 'diteruskan' (antrean), 'dalam_penanganan' (cakupan monitoring),
     * atau laporan pernah 'forwarded' (riwayat diteruskan/selesai/
     * dikembalikan). Selain itu 404 — konsisten dengan pola keamanan
     * aplikasi (samarkan eksistensi resource tak berhak).
     */
    private function authorizeReport(Report $report): void
    {
        if ($report->status === 'diteruskan' || $report->status === 'dalam_penanganan') {
            return;
        }

        $everForwarded = $report->histories()
            ->where('action', 'forwarded')
            ->exists();

        if (! $everForwarded) {
            abort(404);
        }
    }

    public function show(Report $report): View
    {
        $this->authorizeReport($report);

        $report->load([
            'student.userBaru', 'schoolClass', 'category',
            'assignedTeacher.userBaru', 'attachments',
            'histories.userBaru',
        ]);

        $forwardHistory = $report->histories
            ->where('action', 'forwarded')
            ->sortByDesc('id')
            ->first();

        $canAct = $report->status === 'diteruskan';

        return view('kepala-sekolah.reports.show', compact('report', 'forwardHistory', 'canAct'));
    }

    public function attachment(Report $report, ReportAttachment $attachment): BinaryFileResponse
    {
        if ((int) $attachment->report_id !== (int) $report->id) {
            abort(404);
        }

        $this->authorizeReport($report);

        $absolutePath = Storage::disk('local')->path($attachment->file_patch);

        if (! is_file($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath);
    }

    public function complete(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $updated = 0;

        DB::transaction(function () use ($report, $validated, &$updated) {
            $updated = Report::where('id', $report->id)
                ->where('status', 'diteruskan')
                ->update([
                    'status' => 'selesai',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                ReportHistory::create([
                    'report_id' => $report->id,
                    'user_id' => Auth::id(),
                    'action' => 'completed',
                    'from_status' => 'diteruskan',
                    'to_status' => 'selesai',
                    'note' => $validated['note'],
                ]);

                // Notifikasi ke siswa pemilik laporan — hanya bila complete
                // Kepsek berhasil, di dalam transaction yang sama.
                $studentUserId = (int) $report->student()->value('user_id');

                if ($studentUserId > 0) {
                    $exists = Notification::where('user_id', $studentUserId)
                        ->where('report_id', $report->id)
                        ->where('title', 'Laporan Selesai oleh Kepala Sekolah')
                        ->exists();

                    if (! $exists) {
                        Notification::create([
                            'user_id' => $studentUserId,
                            'report_id' => $report->id,
                            'title' => 'Laporan Selesai oleh Kepala Sekolah',
                            'message' => 'Laporan Anda telah selesai ditindaklanjuti oleh Kepala Sekolah.',
                            'is_read' => false,
                        ]);
                    }
                }
            }
        });

        if ($updated === 0) {
            return back()->with('error', 'Laporan tidak dapat diselesaikan. Pastikan laporan masih berstatus diteruskan.');
        }

        return redirect()->route('kepala-sekolah.reports.show', $report->id)
            ->with('success', 'Laporan berhasil diselesaikan.');
    }

    public function returnToTeacher(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $updated = 0;

        DB::transaction(function () use ($report, $validated, &$updated) {
            $updated = Report::where('id', $report->id)
                ->where('status', 'diteruskan')
                ->update([
                    'status' => 'dalam_penanganan',
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                ReportHistory::create([
                    'report_id' => $report->id,
                    'user_id' => Auth::id(),
                    'action' => 'returned',
                    'from_status' => 'diteruskan',
                    'to_status' => 'dalam_penanganan',
                    'note' => $validated['note'],
                ]);

                // Notifikasi ke Guru penerima kembali — hanya bila return
                // berhasil, di dalam transaction yang sama. Recipient dari
                // assigned_teacher_id saat ini (sudah memperhitungkan
                // takeover), bukan dari history lama atau request.
                $teacherUserId = (int) Teacher::where('id', $report->assigned_teacher_id)->value('user_id');

                if ($teacherUserId > 0) {
                    // Guard per siklus return: izinkan event return berikutnya
                    // (count returned > jumlah notifikasi return), cegah ganda
                    // untuk event yang sama.
                    $returnedCount = ReportHistory::where('report_id', $report->id)
                        ->where('action', 'returned')
                        ->count();
                    $notifCount = Notification::where('user_id', $teacherUserId)
                        ->where('report_id', $report->id)
                        ->where('title', 'Laporan Dikembalikan')
                        ->count();

                    if ($notifCount < $returnedCount) {
                        Notification::create([
                            'user_id' => $teacherUserId,
                            'report_id' => $report->id,
                            'title' => 'Laporan Dikembalikan',
                            'message' => 'Laporan dikembalikan oleh Kepala Sekolah untuk ditindaklanjuti kembali.',
                            'is_read' => false,
                        ]);
                    }
                }
            }
        });

        if ($updated === 0) {
            return back()->with('error', 'Laporan tidak dapat dikembalikan. Pastikan laporan masih berstatus diteruskan.');
        }

        return redirect()->route('kepala-sekolah.reports.index')
            ->with('success', 'Laporan berhasil dikembalikan ke Guru Piket.');
    }
}
