<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\ClassDutyReport;
use App\Models\ClassDutyReportHistory;
use App\Models\Notification;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ClassDutyService;
use App\Services\GuruDutyService;
use App\Support\SecureAttachment;
use App\Models\ClassDutyReportAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClassDutyController extends Controller
{
    private function currentTeacher(Request $request): Teacher
    {
        try {
            return Teacher::resolveForUser($request->user());
        } catch (\App\Exceptions\MissingTeacherProfile $e) {
            abort(403, $e->getMessage());
        }
    }

public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);
        $day = ClassDutyService::todayDay();
        $date = ClassDutyService::todayDate();
        $onDutyDay = GuruDutyService::isOnDutyDay($teacher);

        $query = SchoolClass::with(['major'])
            ->withCount(['students' => function ($q) {
                $q->whereHas('userBaru', function ($uq) {
                    $uq->where('status', 'aktif');
                });
            }])
            ->where('status', 'aktif')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('major', function ($mq) use ($search) {
                        $mq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (in_array($request->input('status'), ['terjadwal', 'tidak_dijadwalkan', 'tidak_ada_piket'], true)) {
            $status = $request->input('status');
            $filterScheduledIds = $day === null
                ? collect()
                : \App\Models\ClassDutySchedule::where('day', $day)->pluck('class_id');
            if ($status === 'terjadwal') {
                $query->whereIn('id', $filterScheduledIds->isEmpty() ? [0] : $filterScheduledIds);
            } elseif ($status === 'tidak_ada_piket') {
                // Kelas yang punya jadwal tapi tidak ada siswa terjadwal
                $classIdsWithStudents = $day === null
                    ? collect()
                    : \App\Models\ClassDutyStudentSchedule::where('day', $day)->pluck('class_id');
                $classIdsWithSchedule = $filterScheduledIds;
                $classIdsWithoutStudents = $classIdsWithSchedule->diff($classIdsWithStudents);
                $query->whereIn('id', $classIdsWithoutStudents->isEmpty() ? [0] : $classIdsWithoutStudents);
            } else {
                $query->when($filterScheduledIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $filterScheduledIds));
            }
        }

        $classes = $query->paginate(12)->withQueryString();

        $scheduledIds = $day === null ? collect() : \App\Models\ClassDutySchedule::where('day', $day)->pluck('class_id');
        $reports = ClassDutyReport::where('report_date', $date)
            ->whereIn('class_id', $classes->getCollection()->pluck('id')->all() ?: [0])
            ->with(['student.userBaru', 'verifiedBy.userBaru'])
            ->get()
            ->keyBy('class_id');

        $rows = $classes->getCollection()->map(function ($class) use ($scheduledIds, $reports, $day, $date) {
            $status = ClassDutyService::statusFor((int) $class->id, $date, $day);
            $report = $reports->get($class->id);

            return [
                'class' => $class,
                'scheduled' => $scheduledIds->contains($class->id),
                'status' => $status,
                'label' => ClassDutyService::statusLabel($status),
                'report' => $report,
                'reporter' => $report?->student?->userBaru?->name,
                'sent_at' => $report?->created_at,
                'verifier' => $report?->verifiedBy?->userBaru?->name,
            ];
        });

        $classes->setCollection($rows);

        // Ringkasan aktual tanggal berjalan (tanpa angka statis).
        $allActive = SchoolClass::where('status', 'aktif')->pluck('id');
        $scheduledCount = $day === null ? 0 : \App\Models\ClassDutySchedule::where('day', $day)->whereIn('class_id', $allActive)->count();
        $todayReports = ClassDutyReport::where('report_date', $date);
        $waitingCount = (clone $todayReports)->where('status', ClassDutyReport::STATUS_MENUNGGU)->count();
        $fixCount = (clone $todayReports)->where('status', ClassDutyReport::STATUS_PERBAIKI)->count();
        $doneCount = (clone $todayReports)->where('status', ClassDutyReport::STATUS_DISETUJUI)->count();
        $missingCount = max(0, $scheduledCount - (clone $todayReports)->count());
        $noStudentsScheduledCount = $day === null ? 0 : \App\Models\ClassDutySchedule::where('day', $day)
            ->whereIn('class_id', $allActive)
            ->whereNotIn('class_id', function ($q) use ($day) {
                $q->select('class_id')->from('class_duty_student_schedules')->where('day', $day);
            })
            ->count();

        $serverNow = ClassDutyService::now();

        return view('guru.class_duty.index', compact(
            'teacher', 'day', 'date', 'onDutyDay', 'classes',
            'scheduledCount', 'missingCount', 'waitingCount', 'fixCount', 'doneCount',
            'noStudentsScheduledCount',
            'serverNow'
        ));
    }

    public function show(Request $request, SchoolClass $class): View
    {
        $teacher = $this->currentTeacher($request);
        $day = ClassDutyService::todayDay();
        $date = ClassDutyService::todayDate();
        $onDutyDay = GuruDutyService::isOnDutyDay($teacher);

        $class->load(['major']);

        $scheduled = ClassDutyService::isScheduled((int) $class->id, $day);
        $status = ClassDutyService::statusFor((int) $class->id, $date, $day);
        $report = ClassDutyService::reportFor((int) $class->id, $date);

        if ($report) {
            $report->load(['student.userBaru', 'verifiedBy.userBaru', 'attachments', 'histories.userBaru']);
        }

        // Model piket: hanya siswa yang dijadwalkan pada hari tersebut.
        $students = collect();
        if ($scheduled) {
            $students = \App\Models\ClassDutyStudentSchedule::where('class_id', $class->id)
                ->where('day', $day)
                ->with(['student.userBaru'])
                ->get()
                ->map(fn ($schedule) => $schedule->student)
                ->filter(fn ($student) => $student && $student->userBaru && $student->userBaru->status === 'aktif')
                ->sortBy('nis')
                ->values();
        }

        return view('guru.class_duty.show', compact(
            'teacher', 'day', 'date', 'onDutyDay',
            'class', 'scheduled', 'status', 'report', 'students'
        ));
    }

    public function reportDetail(Request $request, ClassDutyReport $classDutyReport): View
    {
        $teacher = $this->currentTeacher($request);
        $classDutyReport->load(['schoolClass.major', 'student.userBaru', 'verifiedBy.userBaru', 'attachments', 'histories.userBaru']);

        return view('guru.class_duty.report', compact('teacher', 'classDutyReport'));
    }

    public function attachment(Request $request, ClassDutyReport $classDutyReport, ClassDutyReportAttachment $attachment): BinaryFileResponse
    {
        $this->currentTeacher($request);

        if ((int) $attachment->class_duty_report_id !== (int) $classDutyReport->id) {
            abort(404);
        }

        $resolved = SecureAttachment::resolve($attachment->file_patch);

        if ($resolved === null) {
            abort(404);
        }

        return SecureAttachment::response($resolved);
    }

    public function approve(Request $request, ClassDutyReport $classDutyReport): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $check = GuruDutyService::canClaim($teacher);

        if (! $check['ok']) {
            return back()->with('error', $check['message']);
        }

        $updated = 0;

        DB::transaction(function () use ($classDutyReport, $teacher, $validated, &$updated) {
            $updated = ClassDutyReport::where('id', $classDutyReport->id)
                ->whereIn('status', [ClassDutyReport::STATUS_MENUNGGU, ClassDutyReport::STATUS_PERBAIKI])
                ->update([
                    'status' => ClassDutyReport::STATUS_DISETUJUI,
                    'verified_by_teacher_id' => $teacher->id,
                    'verified_at' => now(),
                    'return_note' => null,
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                ClassDutyReportHistory::create([
                    'class_duty_report_id' => $classDutyReport->id,
                    'user_id' => Auth::id(),
                    'action' => 'approved',
                    'from_status' => $classDutyReport->status,
                    'to_status' => ClassDutyReport::STATUS_DISETUJUI,
                    'note' => $validated['note'],
                    'created_at' => now(),
                ]);

                $this->notifyStudent($classDutyReport, 'Laporan Piket Disetujui', 'Laporan piket kelas Anda telah disetujui oleh Guru Piket.');
            }
        });

        if ($updated === 0) {
            return back()->with('error', 'Laporan tidak dapat disetujui. Pastikan laporan masih menunggu verifikasi atau perlu diperbaiki.');
        }

        return redirect()->route('guru.class-duty.report', $classDutyReport->id)
            ->with('success', 'Laporan piket berhasil disetujui.');
    }

    public function returnToStudent(Request $request, ClassDutyReport $classDutyReport): RedirectResponse
    {
        $teacher = $this->currentTeacher($request);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $check = GuruDutyService::canClaim($teacher);

        if (! $check['ok']) {
            return back()->with('error', $check['message']);
        }

        $updated = 0;

        DB::transaction(function () use ($classDutyReport, $teacher, $validated, &$updated) {
            $updated = ClassDutyReport::where('id', $classDutyReport->id)
                ->where('status', ClassDutyReport::STATUS_MENUNGGU)
                ->update([
                    'status' => ClassDutyReport::STATUS_PERBAIKI,
                    'verified_by_teacher_id' => $teacher->id,
                    'verified_at' => now(),
                    'return_note' => $validated['note'],
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                ClassDutyReportHistory::create([
                    'class_duty_report_id' => $classDutyReport->id,
                    'user_id' => Auth::id(),
                    'action' => 'returned',
                    'from_status' => ClassDutyReport::STATUS_MENUNGGU,
                    'to_status' => ClassDutyReport::STATUS_PERBAIKI,
                    'note' => $validated['note'],
                    'created_at' => now(),
                ]);

                $this->notifyStudent($classDutyReport, 'Laporan Piket Dikembalikan', 'Laporan piket kelas Anda dikembalikan oleh Guru Piket: '.$validated['note']);
            }
        });

        if ($updated === 0) {
            return back()->with('error', 'Laporan tidak dapat dikembalikan. Pastikan laporan masih menunggu verifikasi.');
        }

        return redirect()->route('guru.class-duty.report', $classDutyReport->id)
            ->with('success', 'Laporan piket berhasil dikembalikan.');
    }

    private function notifyStudent(ClassDutyReport $report, string $title, string $message): void
    {
        $studentUserId = (int) $report->student()->value('user_id');

        if ($studentUserId <= 0) {
            return;
        }

        $exists = Notification::where('user_id', $studentUserId)
            ->where('title', $title)
            ->where('message', $message)
            ->exists();

        if (! $exists) {
            Notification::create([
                'user_id' => $studentUserId,
                'report_id' => null,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
            ]);
        }
    }
}
