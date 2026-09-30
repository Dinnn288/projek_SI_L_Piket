<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportHistory;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Services\GuruDutyService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    private function currentTeacher(Request $request): Teacher
    {
        $teacher = $request->user()->teacher()->first();

        if (! $teacher) {
            abort(403, 'Data guru tidak ditemukan.');
        }

        return $teacher;
    }

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);
        $day = GuruDutyService::todayDay();
        $onDutyDay = GuruDutyService::isOnDutyDay($teacher);
        $withinHours = GuruDutyService::isWithinHours();

        $waiting = collect();
        $continued = collect();

        if ($onDutyDay) {
            $waiting = Report::where('status', 'menunggu')
                ->whereNull('assigned_teacher_id')
                ->with(['category', 'schoolClass', 'student.userBaru'])
                ->withCount('attachments')
                ->latest()
                ->get();

            $continued = Report::where('status', 'dalam_penanganan')
                ->whereNotNull('assigned_teacher_id')
                ->where('assigned_teacher_id', '!=', $teacher->id)
                ->where('assigned_at', '<', Carbon::today())
                ->with(['category', 'schoolClass', 'student.userBaru', 'assignedTeacher.userBaru'])
                ->withCount('attachments')
                ->latest()
                ->get();
        }

        $mine = $teacher->assignedReports()
            ->with(['category', 'schoolClass'])
            ->withCount('attachments')
            ->latest()
            ->get();

        return view('guru.reports.index', compact(
            'teacher', 'day', 'onDutyDay', 'withinHours', 'waiting', 'continued', 'mine'
        ));
    }

    public function show(Request $request, Report $report): View
    {
        $teacher = $this->currentTeacher($request);
        $day = GuruDutyService::todayDay();
        $report->load(['category', 'schoolClass', 'student.userBaru', 'attachments', 'assignedTeacher.userBaru', 'histories.userBaru']);

        $isWaiting = $report->status === 'menunggu' && $report->assigned_teacher_id === null;
        $isMine = (int) $report->assigned_teacher_id === (int) $teacher->id;
        $isContinuable = GuruDutyService::isContinuable($report) && ! $isMine;

        if ($isWaiting) {
            if (! GuruDutyService::isOnDutyDay($teacher)) {
                abort(403, 'Laporan ini hanya dapat dilihat Guru Piket yang bertugas hari ini.');
            }
            $claim = GuruDutyService::canClaim($teacher);
            $canClaim = $claim['ok'];
            $claimMessage = $claim['message'];
            $canTakeover = false;
            $takeoverMessage = '';
        } elseif ($isContinuable) {
            if (! GuruDutyService::isOnDutyDay($teacher)) {
                abort(403, 'Laporan lanjutan ini hanya dapat dilihat Guru Piket yang bertugas hari ini.');
            }
            $takeover = GuruDutyService::canTakeover($teacher, $report);
            $canClaim = false;
            $claimMessage = '';
            $canTakeover = $takeover['ok'];
            $takeoverMessage = $takeover['message'];
        } elseif ($isMine) {
            $canClaim = false;
            $claimMessage = '';
            $canTakeover = false;
            $takeoverMessage = '';
        } else {
            abort(403, 'Anda tidak berhak mengakses laporan ini.');
        }

        return view('guru.reports.show', compact('teacher', 'day', 'report', 'isWaiting', 'isMine', 'isContinuable', 'canClaim', 'claimMessage', 'canTakeover', 'takeoverMessage'));
    }

    public function attachment(Request $request, Report $report, ReportAttachment $attachment): BinaryFileResponse
    {
        $teacher = $this->currentTeacher($request);

        if ((int) $attachment->report_id !== (int) $report->id) {
            abort(404);
        }

        $isWaiting = $report->status === 'menunggu' && $report->assigned_teacher_id === null;
        $isMine = (int) $report->assigned_teacher_id === (int) $teacher->id;
        $isContinuable = GuruDutyService::isContinuable($report) && ! $isMine;

        if ($isWaiting || $isContinuable) {
            if (! GuruDutyService::isOnDutyDay($teacher)) {
                abort(403, 'Anda tidak berhak mengakses laporan ini.');
            }
        } elseif (! $isMine) {
            abort(403, 'Anda tidak berhak mengakses laporan ini.');
        }

        $absolutePath = Storage::disk('local')->path($attachment->file_patch);

        if (! is_file($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath);
    }

    public function claim(Request $request, Report $report): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        if ((int) $report->assigned_teacher_id === (int) $teacher->id) {
            return back()->with('error', 'Anda sudah menangani laporan ini.');
        }

        $check = GuruDutyService::canClaim($teacher);

        if (! $check['ok']) {
            return back()->with('error', $check['message']);
        }

        $updated = 0;

        DB::transaction(function () use ($report, $teacher, &$updated) {
            $updated = Report::where('id', $report->id)
                ->where('status', 'menunggu')
                ->whereNull('assigned_teacher_id')
                ->update([
                    'assigned_teacher_id' => $teacher->id,
                    'status' => 'dalam_penanganan',
                    'assigned_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                ReportHistory::create([
                    'report_id' => $report->id,
                    'user_id' => Auth::id(),
                    'action' => 'claimed',
                    'from_status' => 'menunggu',
                    'to_status' => 'dalam_penanganan',
                    'note' => null,
                ]);

                // Notifikasi ke siswa pemilik laporan — hanya bila claim
                // berhasil, di dalam transaction yang sama.
                $studentUserId = (int) $report->student()->value('user_id');

                if ($studentUserId > 0) {
                    $exists = Notification::where('user_id', $studentUserId)
                        ->where('report_id', $report->id)
                        ->where('title', 'Laporan Sedang Ditangani')
                        ->exists();

                    if (! $exists) {
                        Notification::create([
                            'user_id' => $studentUserId,
                            'report_id' => $report->id,
                            'title' => 'Laporan Sedang Ditangani',
                            'message' => 'Laporan Anda sedang ditangani oleh Guru Piket.',
                            'is_read' => false,
                        ]);
                    }
                }
            }
        });

        if ($updated === 0) {
            return back()->with('error', 'Laporan sudah diambil oleh Guru Piket lain.');
        }

        return redirect()->route('guru.reports.show', $report->id)
            ->with('success', 'Laporan berhasil diambil.');
    }

    public function takeover(Request $request, Report $report): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $previousTeacherId = (int) $report->assigned_teacher_id;
        $previousAssignedAt = $report->assigned_at ? Carbon::parse($report->assigned_at) : null;

        if ($previousTeacherId === (int) $teacher->id) {
            return back()->with('error', 'Laporan ini masih dalam penanganan Anda.');
        }

        $continuable = GuruDutyService::isContinuable($report);

        if (! $continuable) {
            if ($report->status === 'dalam_penanganan' && $previousTeacherId !== 0) {
                return back()->with('error', 'Laporan sudah diambil oleh Guru Piket lain.');
            }

            return back()->with('error', 'Laporan ini tidak dapat diambil alih.');
        }

        $check = GuruDutyService::canClaim($teacher);

        if (! $check['ok']) {
            return back()->with('error', $check['message']);
        }

        $updated = 0;

        DB::transaction(function () use ($report, $teacher, $previousTeacherId, $previousAssignedAt, &$updated) {
            $updated = Report::where('id', $report->id)
                ->where('status', 'dalam_penanganan')
                ->where('assigned_teacher_id', $previousTeacherId)
                ->where('assigned_at', $previousAssignedAt?->format('Y-m-d H:i:s'))
                ->update([
                    'assigned_teacher_id' => $teacher->id,
                    'assigned_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                ReportHistory::create([
                    'report_id' => $report->id,
                    'user_id' => Auth::id(),
                    'action' => 'claimed',
                    'from_status' => 'dalam_penanganan',
                    'to_status' => 'dalam_penanganan',
                    'note' => 'Diambil alih oleh Guru Piket berikutnya.',
                ]);
            }
        });

        if ($updated === 0) {
            return back()->with('error', 'Laporan sudah diambil oleh Guru Piket lain.');
        }

        return redirect()->route('guru.reports.show', $report->id)
            ->with('success', 'Laporan berhasil diambil alih.');
    }

    public function complete(Request $request, Report $report): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $updated = 0;

        DB::transaction(function () use ($report, $teacher, $validated, &$updated) {
            $updated = Report::where('id', $report->id)
                ->where('assigned_teacher_id', $teacher->id)
                ->where('status', 'dalam_penanganan')
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
                    'from_status' => 'dalam_penanganan',
                    'to_status' => 'selesai',
                    'note' => $validated['note'],
                ]);

                // Notifikasi ke siswa pemilik laporan — hanya bila complete
                // berhasil, di dalam transaction yang sama.
                $studentUserId = (int) $report->student()->value('user_id');

                if ($studentUserId > 0) {
                    $exists = Notification::where('user_id', $studentUserId)
                        ->where('report_id', $report->id)
                        ->where('title', 'Laporan Selesai')
                        ->exists();

                    if (! $exists) {
                        Notification::create([
                            'user_id' => $studentUserId,
                            'report_id' => $report->id,
                            'title' => 'Laporan Selesai',
                            'message' => 'Laporan Anda telah selesai ditangani oleh Guru Piket.',
                            'is_read' => false,
                        ]);
                    }
                }
            }
        });

        if ($updated === 0) {
            return back()->with('error', 'Laporan tidak dapat diselesaikan. Pastikan laporan masih dalam penanganan Anda.');
        }

        return redirect()->route('guru.reports.show', $report->id)
            ->with('success', 'Laporan berhasil diselesaikan.');
    }

    public function forward(Request $request, Report $report): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $updated = 0;

        DB::transaction(function () use ($report, $teacher, $validated, &$updated) {
            $updated = Report::where('id', $report->id)
                ->where('assigned_teacher_id', $teacher->id)
                ->where('status', 'dalam_penanganan')
                ->update([
                    'status' => 'diteruskan',
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                ReportHistory::create([
                    'report_id' => $report->id,
                    'user_id' => Auth::id(),
                    'action' => 'forwarded',
                    'from_status' => 'dalam_penanganan',
                    'to_status' => 'diteruskan',
                    'note' => $validated['note'],
                ]);

                // Notifikasi ke seluruh Kepala Sekolah — hanya bila forward
                // berhasil, di dalam transaction yang sama.
                $kepsekIds = UserBaru::where('role', 'kepala_sekolah')
                    ->where('status', 'aktif')
                    ->pluck('id');

                foreach ($kepsekIds as $kepsekId) {
                    $exists = Notification::where('user_id', $kepsekId)
                        ->where('report_id', $report->id)
                        ->where('title', 'Laporan Diteruskan')
                        ->exists();

                    if (! $exists) {
                        Notification::create([
                            'user_id' => $kepsekId,
                            'report_id' => $report->id,
                            'title' => 'Laporan Diteruskan',
                            'message' => 'Ada laporan yang diteruskan oleh Guru Piket dan memerlukan tindak lanjut.',
                            'is_read' => false,
                        ]);
                    }
                }
            }
        });

        if ($updated === 0) {
            return back()->with('error', 'Laporan tidak dapat diteruskan. Pastikan laporan masih dalam penanganan Anda.');
        }

        return redirect()->route('guru.reports.show', $report->id)
            ->with('success', 'Laporan berhasil diteruskan ke Kepala Sekolah.');
    }
}
