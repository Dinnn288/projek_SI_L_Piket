<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\ClassDutyReport;
use App\Models\ClassDutyReportAttachment;
use App\Models\ClassDutyReportHistory;
use App\Models\Notification;
use App\Models\Student;
use App\Services\ClassDutyService;
use App\Services\GuruDutyService;
use App\Models\DutySchedule;
use App\Support\SecureAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClassDutyController extends Controller
{
    private function currentStudent(Request $request): Student
    {
        $student = $request->user()->student()->with('schoolClass.major')->first();

        if (! $student || ! $student->class_id) {
            abort(403, 'Data siswa tidak ditemukan.');
        }

        if (($student->userBaru?->status ?? $request->user()->status) !== 'aktif') {
            abort(403, 'Akun tidak aktif.');
        }

        return $student;
    }

    public function index(Request $request): View
    {
        $student = $this->currentStudent($request);
        $day = ClassDutyService::todayDay();
        $date = ClassDutyService::todayDate();
        $scheduled = ClassDutyService::isStudentScheduled((int) $student->id, $day);
        $status = ClassDutyService::statusFor((int) $student->class_id, $date, $day);
        $report = ClassDutyService::reportFor((int) $student->class_id, $date);

        if ($report) {
            $report->load(['student.userBaru', 'verifiedBy.userBaru', 'attachments', 'histories.userBaru']);
        }

        return view('siswa.class_duty.index', compact('student', 'day', 'date', 'scheduled', 'status', 'report'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $student = $this->currentStudent($request);
        $day = ClassDutyService::todayDay();
        $date = ClassDutyService::todayDate();

        if (! ClassDutyService::isStudentScheduled((int) $student->id, $day)) {
            return redirect()->route('siswa.class-duty.index')
                ->with('error', 'Anda tidak dijadwalkan piket hari ini.');
        }

        $existing = ClassDutyService::reportFor((int) $student->class_id, $date);

        if ($existing) {
            return redirect()->route('siswa.class-duty.index')
                ->with('error', 'Laporan piket hari ini sudah dikirim. Lihat status laporan yang sudah ada.');
        }

        return view('siswa.class_duty.create', [
            'student' => $student,
            'day' => $day,
            'date' => $date,
            'checklistItems' => ClassDutyService::CHECKLIST_ITEMS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $this->currentStudent($request);
        $day = ClassDutyService::todayDay();
        $date = ClassDutyService::todayDate();

        if (! ClassDutyService::isStudentScheduled((int) $student->id, $day)) {
            return back()->with('error', 'Anda tidak dijadwalkan piket hari ini.')->withInput();
        }

        $validated = $request->validate([
            'checklist' => ['required', 'array', 'min:1'],
            'checklist.*' => ['string', 'in:'.implode(',', array_keys(ClassDutyService::CHECKLIST_ITEMS))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        // Cegah ganda di level aplikasi (jaring pertama; UNIQUE DB jaring akhir).
        $existing = ClassDutyService::reportFor((int) $student->class_id, $date);

        if ($existing) {
            return redirect()->route('siswa.class-duty.index')
                ->with('error', 'Laporan piket hari ini sudah dikirim oleh perwakilan kelas. Lihat status laporan yang sudah ada.');
        }

        $storedPaths = [];

        try {
            $report = DB::transaction(function () use ($student, $date, $validated, $request, &$storedPaths) {
                $report = ClassDutyReport::create([
                    'class_id' => $student->class_id,
                    'report_date' => $date,
                    'student_id' => $student->id,
                    'title' => 'Laporan Pelaksanaan Piket Kelas',
                    'checklist' => array_values(array_unique($validated['checklist'])),
                    'notes' => $validated['notes'] ?? null,
                    'status' => ClassDutyReport::STATUS_MENUNGGU,
                ]);

                foreach ($request->file('photos', []) as $photo) {
                    $path = $photo->store('reports/piket-kelas', 'local');
                    $storedPaths[] = $path;

                    $report->attachments()->create([
                        'file_patch' => $path,
                        'original_name' => $photo->getClientOriginalName(),
                        'file_size' => $photo->getSize(),
                        'mime_type' => $photo->getMimeType() ?? $photo->getClientMimeType(),
                    ]);
                }

                ClassDutyReportHistory::create([
                    'class_duty_report_id' => $report->id,
                    'user_id' => Auth::id(),
                    'action' => 'created',
                    'from_status' => null,
                    'to_status' => ClassDutyReport::STATUS_MENUNGGU,
                    'note' => null,
                    'created_at' => now(),
                ]);

                // Notifikasi ke Guru Piket hari ini (dalam transaksi yang sama).
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
                        Notification::create([
                            'user_id' => $userId,
                            'report_id' => null,
                            'title' => 'Laporan Piket Kelas Baru',
                            'message' => 'Laporan piket kelas '.$student->schoolClass?->name.' tanggal '.$date.' menunggu verifikasi.',
                            'is_read' => false,
                        ]);
                    }
                }

                return $report;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            return redirect()->route('siswa.class-duty.index')
                ->with('error', 'Laporan piket hari ini sudah dikirim oleh perwakilan kelas. Lihat status laporan yang sudah ada.');
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            report($e);

            return back()->with('error', 'Gagal menyimpan laporan. Silakan coba lagi.')->withInput();
        }

        return redirect()->route('siswa.class-duty.index')
            ->with('success', 'Laporan piket berhasil dikirim dan menunggu verifikasi.');
    }

    public function resubmit(Request $request, ClassDutyReport $classDutyReport): RedirectResponse
    {
        $student = $this->currentStudent($request);

        // Otorisasi objek: hanya kelas sendiri + status perlu_diperbaiki.
        if ((int) $classDutyReport->class_id !== (int) $student->class_id) {
            abort(404);
        }

        if ($classDutyReport->status !== ClassDutyReport::STATUS_PERBAIKI) {
            return back()->with('error', 'Laporan ini tidak perlu diperbaiki.');
        }

        $validated = $request->validate([
            'checklist' => ['required', 'array', 'min:1'],
            'checklist.*' => ['string', 'in:'.implode(',', array_keys(ClassDutyService::CHECKLIST_ITEMS))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $storedPaths = [];

        DB::beginTransaction();

        try {
            $updated = ClassDutyReport::where('id', $classDutyReport->id)
                ->where('status', ClassDutyReport::STATUS_PERBAIKI)
                ->update([
                    'checklist' => array_values(array_unique($validated['checklist'])),
                    'notes' => $validated['notes'] ?? null,
                    'status' => ClassDutyReport::STATUS_MENUNGGU,
                    'return_note' => null,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                DB::rollBack();

                return back()->with('error', 'Laporan sudah berubah. Muat ulang halaman.');
            }

            foreach ($request->file('photos', []) as $photo) {
                $path = $photo->store('reports/piket-kelas', 'local');
                $storedPaths[] = $path;

                $classDutyReport->attachments()->create([
                    'file_patch' => $path,
                    'original_name' => $photo->getClientOriginalName(),
                    'file_size' => $photo->getSize(),
                    'mime_type' => $photo->getMimeType() ?? $photo->getClientMimeType(),
                ]);
            }

            ClassDutyReportHistory::create([
                'class_duty_report_id' => $classDutyReport->id,
                'user_id' => Auth::id(),
                'action' => 'resubmitted',
                'from_status' => ClassDutyReport::STATUS_PERBAIKI,
                'to_status' => ClassDutyReport::STATUS_MENUNGGU,
                'note' => 'Laporan diperbaiki dan dikirim ulang.',
                'created_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            report($e);

            return back()->with('error', 'Gagal mengirim ulang laporan. Silakan coba lagi.')->withInput();
        }

        return redirect()->route('siswa.class-duty.index')
            ->with('success', 'Perbaikan laporan berhasil dikirim dan menunggu verifikasi.');
    }

    public function attachment(Request $request, ClassDutyReport $classDutyReport, ClassDutyReportAttachment $attachment): BinaryFileResponse
    {
        $student = $this->currentStudent($request);

        if ((int) $classDutyReport->class_id !== (int) $student->class_id) {
            abort(404);
        }

        if ((int) $attachment->class_duty_report_id !== (int) $classDutyReport->id) {
            abort(404);
        }

        $resolved = SecureAttachment::resolve($attachment->file_patch);

        if ($resolved === null) {
            abort(404);
        }

        return SecureAttachment::response($resolved);
    }
}
