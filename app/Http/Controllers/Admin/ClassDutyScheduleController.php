<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassDutySchedule;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassDutyScheduleController extends Controller
{
    public const DAYS = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];

    private function dayLabel(string $day): string
    {
        return match ($day) {
            'senin' => 'Senin',
            'selasa' => 'Selasa',
            'rabu' => 'Rabu',
            'kamis' => 'Kamis',
            'jumat' => 'Jumat',
            default => $day,
        };
    }

    public function index(Request $request): View
    {
        $query = ClassDutySchedule::with(['schoolClass.major']);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('day', 'like', "%{$search}%")
                    ->orWhereHas('schoolClass', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhereHas('major', function ($mq) use ($search) {
                                $mq->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if (in_array($request->input('day'), self::DAYS, true)) {
            $query->where('day', $request->input('day'));
        }

        $schedules = $query->orderBy('day')->orderBy('class_id')->get();
        $grouped = $schedules->groupBy('day');

        return view('admin.class_duty_schedules.index', [
            'schedules' => $schedules,
            'grouped' => $grouped,
            'days' => self::DAYS,
        ]);
    }

    public function create(): View
    {
        $classes = SchoolClass::with('major')
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get();

        return view('admin.class_duty_schedules.create', [
            'classes' => $classes,
            'days' => self::DAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'day' => ['required', 'in:senin,selasa,rabu,kamis,jumat'],
        ]);

        $class = SchoolClass::whereKey($data['class_id'])->first();

        if (! $class || $class->status !== 'aktif') {
            return back()
                ->withErrors(['class_id' => 'Kelas tidak aktif atau tidak ditemukan.'])
                ->withInput();
        }

        $duplicate = ClassDutySchedule::where('class_id', $data['class_id'])
            ->where('day', $data['day'])
            ->exists();

        if ($duplicate) {
            return back()
                ->withErrors(['day' => 'Kelas '.$class->name.' sudah memiliki jadwal pada hari '.$this->dayLabel($data['day']).'.'])
                ->withInput();
        }

        ClassDutySchedule::create($data);

        return redirect()->route('admin.class-duty-schedules.index')
            ->with('success', 'Jadwal piket kelas berhasil ditambahkan.');
    }

    public function edit(ClassDutySchedule $classDutySchedule): View
    {
        $classDutySchedule->load('schoolClass.major');
        $classes = SchoolClass::with('major')
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get();

        return view('admin.class_duty_schedules.edit', [
            'schedule' => $classDutySchedule,
            'classes' => $classes,
            'days' => self::DAYS,
        ]);
    }

    public function update(Request $request, ClassDutySchedule $classDutySchedule): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'day' => ['required', 'in:senin,selasa,rabu,kamis,jumat'],
        ]);

        $class = SchoolClass::whereKey($data['class_id'])->first();

        if (! $class || $class->status !== 'aktif') {
            return back()
                ->withErrors(['class_id' => 'Kelas tidak aktif atau tidak ditemukan.'])
                ->withInput();
        }

        $duplicate = ClassDutySchedule::where('class_id', $data['class_id'])
            ->where('day', $data['day'])
            ->where('id', '!=', $classDutySchedule->id)
            ->exists();

        if ($duplicate) {
            return back()
                ->withErrors(['day' => 'Kelas '.$class->name.' sudah memiliki jadwal pada hari '.$this->dayLabel($data['day']).'.'])
                ->withInput();
        }

        $classDutySchedule->update($data);

        return redirect()->route('admin.class-duty-schedules.index')
            ->with('success', 'Jadwal piket kelas berhasil diperbarui.');
    }

    public function destroy(ClassDutySchedule $classDutySchedule): RedirectResponse
    {
        // Hanya baris jadwal yang dihapus; laporan/riwayat hari lalu tetap utuh.
        $classDutySchedule->delete();

        return redirect()->route('admin.class-duty-schedules.index')
            ->with('success', 'Jadwal piket kelas berhasil dihapus.');
    }
}
