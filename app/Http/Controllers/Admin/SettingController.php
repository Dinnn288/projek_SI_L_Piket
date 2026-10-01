<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public const DEFAULT_START = '07:00';

    public const DEFAULT_END = '16:00';

    public function edit(): View
    {
        return view('admin.settings.operational-hours', [
            'start' => Setting::get('operational_start_time', self::DEFAULT_START),
            'end' => Setting::get('operational_end_time', self::DEFAULT_END),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'operational_start_time' => ['required', 'date_format:H:i'],
            'operational_end_time' => ['required', 'date_format:H:i', 'different:operational_start_time'],
        ]);

        if ($data['operational_end_time'] <= $data['operational_start_time']) {
            return back()
                ->withErrors(['operational_end_time' => 'Jam selesai harus lebih besar dari jam mulai dalam hari yang sama.'])
                ->withInput();
        }

        Setting::set('operational_start_time', $data['operational_start_time']);
        Setting::set('operational_end_time', $data['operational_end_time']);

        return redirect()->route('admin.settings.operational-hours.edit')
            ->with('success', 'Pengaturan jam operasional berhasil disimpan.');
    }
}
