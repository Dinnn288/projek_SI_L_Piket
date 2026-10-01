<?php

namespace Tests\Feature;

use App\Models\DutySchedule;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Services\GuruDutyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalHoursTest extends TestCase
{
    private array $ids = [];

    private function reconnect(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket']);
        DB::reconnect('mysql');
    }

    private function at(string $time): Carbon
    {
        return Carbon::today(GuruDutyService::TIMEZONE)->setTimeFromTimeString($time);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();

        $admin = UserBaru::where('username', 'fop_admin')->first()
            ?: UserBaru::create(['name' => 'Admin FOP', 'username' => 'fop_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);
        $guru = UserBaru::where('username', 'fop_guru')->first()
            ?: UserBaru::create(['name' => 'Guru FOP', 'username' => 'fop_guru', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        $siswa = UserBaru::where('username', 'fop_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa FOP', 'username' => 'fop_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        $kepsek = UserBaru::where('username', 'fop_kepsek')->first()
            ?: UserBaru::create(['name' => 'Kepsek FOP', 'username' => 'fop_kepsek', 'password' => Hash::make('password123'), 'role' => 'kepala_sekolah', 'status' => 'aktif']);
        $teacher = Teacher::where('user_id', $guru->id)->first()
            ?: Teacher::create(['user_id' => $guru->id, 'nip' => 'FOPNIP001']);

        $this->ids = ['admin' => $admin->id, 'guru' => $guru->id, 'siswa' => $siswa->id, 'kepsek' => $kepsek->id, 'teacher' => $teacher->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $tids = Teacher::whereIn('user_id', [$this->ids['guru'] ?? 0])->pluck('id');
                if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->delete();
                Teacher::whereIn('user_id', [$this->ids['guru'] ?? 0])->delete();
                UserBaru::whereIn('id', [$this->ids['admin'] ?? 0, $this->ids['guru'] ?? 0, $this->ids['siswa'] ?? 0, $this->ids['kepsek'] ?? 0])->delete();
            }
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function admin(): UserBaru
    {
        return UserBaru::findOrFail($this->ids['admin']);
    }

    public function test_default_and_timezone(): void
    {
        Setting::whereIn('key', ['operational_start_time', 'operational_end_time'])->delete();
        $this->assertEquals('07:00', GuruDutyService::start());
        $this->assertEquals('16:00', GuruDutyService::end());
        $this->assertEquals('Asia/Jakarta', GuruDutyService::TIMEZONE);
        $this->assertEquals('Asia/Jakarta', GuruDutyService::now()->getTimezone()->getName());
        $this->assertEquals('Asia/Jakarta', config('app.timezone'));
    }

    public function test_admin_can_save_and_roles_rejected(): void
    {
        $this->actingAs($this->admin())->get('/admin/pengaturan/jam-operasional')->assertStatus(200);
        $this->actingAs($this->admin())->put('/admin/pengaturan/jam-operasional', [
            'operational_start_time' => '06:30', 'operational_end_time' => '15:30',
        ])->assertRedirect(route('admin.settings.operational-hours.edit'));
        $this->assertEquals('06:30', GuruDutyService::start());
        $this->assertEquals('15:30', GuruDutyService::end());
        foreach (['siswa', 'guru', 'kepsek'] as $key) {
            $this->refreshApplication();
            $this->reconnect();
            $user = UserBaru::find($this->ids[$key]);
            if ($user) {
                $this->actingAs($user)->get('/admin/pengaturan/jam-operasional')->assertStatus(403);
                $this->actingAs($user)->put('/admin/pengaturan/jam-operasional', [
                    'operational_start_time' => '05:00', 'operational_end_time' => '17:00',
                ])->assertStatus(403);
            }
        }
        $this->refreshApplication();
        $this->reconnect();
        $this->get('/admin/pengaturan/jam-operasional')->assertRedirect('/login');
        // Kembalikan default agar suite lain stabil
        Setting::set('operational_start_time', '07:00');
        Setting::set('operational_end_time', '16:00');
    }

    public function test_validation_rejects_invalid(): void
    {
        $admin = $this->admin();
        // Mulai = selesai
        $this->actingAs($admin)->put('/admin/pengaturan/jam-operasional', [
            'operational_start_time' => '08:00', 'operational_end_time' => '08:00',
        ])->assertSessionHasErrors('operational_end_time');
        // Selesai < mulai
        $this->actingAs($admin)->put('/admin/pengaturan/jam-operasional', [
            'operational_start_time' => '16:00', 'operational_end_time' => '07:00',
        ])->assertSessionHasErrors('operational_end_time');
        // Format invalid
        $this->actingAs($admin)->put('/admin/pengaturan/jam-operasional', [
            'operational_start_time' => 'pagi', 'operational_end_time' => 'sore',
        ])->assertSessionHasErrors(['operational_start_time', 'operational_end_time']);
        $this->assertEquals('07:00', GuruDutyService::start());
        $this->assertEquals('16:00', GuruDutyService::end());
    }

    public function test_boundaries_and_day_rollover(): void
    {
        Setting::set('operational_start_time', '06:30');
        Setting::set('operational_end_time', '15:30');
        $this->assertFalse(GuruDutyService::isWithinHours($this->at('06:29')));
        $this->assertTrue(GuruDutyService::isWithinHours($this->at('06:30')));
        $this->assertTrue(GuruDutyService::isWithinHours($this->at('12:00')));
        $this->assertTrue(GuruDutyService::isWithinHours($this->at('15:30')));
        $this->assertFalse(GuruDutyService::isWithinHours($this->at('15:31')));
        $this->assertFalse(GuruDutyService::isWithinHours($this->at('16:01')));
        // Hari: Senin vs Selasa tidak tertukar (pakai tanggal eksplisit WIB)
        $monday = Carbon::parse('2026-09-28', GuruDutyService::TIMEZONE);
        $tuesday = Carbon::parse('2026-09-29', GuruDutyService::TIMEZONE);
        Carbon::setTestNow($monday->setTime(10, 0));
        $this->assertEquals('senin', GuruDutyService::todayDay());
        Carbon::setTestNow($tuesday->setTime(10, 0));
        $this->assertEquals('selasa', GuruDutyService::todayDay());
        Carbon::setTestNow(null);
        Setting::set('operational_start_time', '07:00');
        Setting::set('operational_end_time', '16:00');
    }

    public function test_multiple_guru_and_claim_uses_db_hours(): void
    {
        Setting::set('operational_start_time', '06:30');
        Setting::set('operational_end_time', '15:30');
        $today = GuruDutyService::todayDay();
        $this->assertNotNull($today);
        $teacher = Teacher::findOrFail($this->ids['teacher']);
        if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
            DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
        }
        // Multi-guru: tambah guru kedua hari yang sama
        $guru2 = UserBaru::create(['name' => 'G2', 'username' => 'fop_guru2', 'password' => Hash::make('x'), 'role' => 'guru', 'status' => 'aktif']);
        $teacher2 = Teacher::create(['user_id' => $guru2->id, 'nip' => 'FOPNIP002']);
        DutySchedule::create(['teacher_id' => $teacher2->id, 'day' => $today]);
        $this->assertTrue(GuruDutyService::isOnDutyDay($teacher->fresh()));
        $this->assertTrue(GuruDutyService::isOnDutyDay($teacher2->fresh()));
        // Claim memakai jam DB: 06:29 ditolak, 06:30 diizinkan (via canClaim)
        Carbon::setTestNow(Carbon::today(GuruDutyService::TIMEZONE)->setTime(6, 29));
        $this->assertFalse(GuruDutyService::canClaim($teacher->fresh())['ok']);
        Carbon::setTestNow(Carbon::today(GuruDutyService::TIMEZONE)->setTime(6, 30));
        $this->assertTrue(GuruDutyService::canClaim($teacher->fresh())['ok']);
        Carbon::setTestNow(null);
        DutySchedule::where('teacher_id', $teacher2->id)->delete();
        $teacher2->delete();
        $guru2->delete();
        Setting::set('operational_start_time', '07:00');
        Setting::set('operational_end_time', '16:00');
    }
}
