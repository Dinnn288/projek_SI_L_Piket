<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DutySchedule;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DutyScheduleController extends Controller
{
    public const DAYS = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu'];

    private function dayLabel(string $day): string
    {
        return match ($day) {
            'senin' => 'Senin',
            'selasa' => 'Selasa',
            'rabu' => 'Rabu',
            'kamis' => 'Kamis',
            'jumat' => 'Jumat',
            'sabtu' => 'Sabtu',
            default => $day,
        };
    }

    private function teachers(): \Illuminate\Database\Eloquent\Collection
    {
        return Teacher::with('userBaru')->orderBy('nip')->get();
    }

    public function index(Request $request): View
    {
        $query = DutySchedule::with(['teacher.userBaru']);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('day', 'like', "%{$search}%")
                    ->orWhereHas('teacher', function ($tq) use ($search) {
                        $tq->where('nip', 'like', "%{$search}%")
                            ->orWhereHas('userBaru', function ($uq) use ($search) {
                                $uq->where('name', 'like', "%{$search}%")
                                    ->orWhere('username', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $schedules = $query->orderBy('day')->orderBy('teacher_id')->get();
        $grouped = $schedules->groupBy('day');

        return view('admin.duty_schedules.index', [
            'schedules' => $schedules,
            'grouped' => $grouped,
            'days' => self::DAYS,
        ]);
    }

    public function create(): View
    {
        return view('admin.duty_schedules.create', [
            'teachers' => $this->teachers(),
            'days' => self::DAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'exists:teachers,id'],
            'day' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu'],
        ]);

        $duplicate = DutySchedule::where('teacher_id', $data['teacher_id'])
            ->where('day', $data['day'])
            ->exists();

        if ($duplicate) {
            return back()
                ->withErrors(['day' => 'Guru tersebut sudah memiliki jadwal pada hari '.$this->dayLabel($data['day']).'.'])
                ->withInput();
        }

        DutySchedule::create($data);

        return redirect()->route('admin.duty-schedules.index')
            ->with('success', 'Jadwal guru piket berhasil ditambahkan.');
    }

    public function edit(DutySchedule $dutySchedule): View
    {
        $dutySchedule->load('teacher.userBaru');

        return view('admin.duty_schedules.edit', [
            'dutySchedule' => $dutySchedule,
            'teachers' => $this->teachers(),
            'days' => self::DAYS,
        ]);
    }

    public function update(Request $request, DutySchedule $dutySchedule): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'exists:teachers,id'],
            'day' => ['required', 'in:senin,selasa,rabu,kamis,jumat,sabtu'],
        ]);

        $duplicate = DutySchedule::where('teacher_id', $data['teacher_id'])
            ->where('day', $data['day'])
            ->where('id', '!=', $dutySchedule->id)
            ->exists();

        if ($duplicate) {
            return back()
                ->withErrors(['day' => 'Guru tersebut sudah memiliki jadwal pada hari '.$this->dayLabel($data['day']).'.'])
                ->withInput();
        }

        $dutySchedule->update($data);

        return redirect()->route('admin.duty-schedules.index')
            ->with('success', 'Jadwal guru piket berhasil diperbarui.');
    }

    public function destroy(DutySchedule $dutySchedule): RedirectResponse
    {
        // Hanya record jadwal yang dihapus; guru/report/history/account tetap utuh.
        $dutySchedule->delete();

        return redirect()->route('admin.duty-schedules.index')
            ->with('success', 'Jadwal guru piket berhasil dihapus.');
    }
}
