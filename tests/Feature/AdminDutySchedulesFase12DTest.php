<?php

namespace Tests\Feature;

use App\Models\DutySchedule;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Services\GuruDutyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDutySchedulesFase12DTest extends TestCase
{
    private array $ids = [];

    private function reconnect(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket']);
        DB::reconnect('mysql');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Storage::fake('local');

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $admin = $mkUser('f12d_admin', 'Admin F12D', 'admin');
        $siswa = $mkUser('f12d_siswa', 'Siswa F12D', 'siswa');
        $guru = $mkUser('f12d_guru', 'Guru F12D', 'guru');
        $kepsek = $mkUser('f12d_kepsek', 'Kepsek F12D', 'kepala_sekolah');
        $tA = $mkUser('f12d_guru_a', 'Guru A F12D', 'guru');
        $tB = $mkUser('f12d_guru_b', 'Guru B F12D', 'guru');
        foreach ([[$tA, 'F12DNIP_A'], [$tB, 'F12DNIP_B']] as [$gu, $nip]) {
            if (! Teacher::where('user_id', $gu->id)->exists()) {
                Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            }
        }

        $this->ids = [
            'admin' => $admin->id, 'siswa' => $siswa->id,
            'guru' => $guru->id, 'kepsek' => $kepsek->id,
            'tA' => $tA->id, 'tB' => UserBaru::where('username', 'f12d_guru_b')->value('id'),
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                // Cleanup eksplisit: jadwal uji by teacher uji, lalu teacher+user uji.
                $uids = UserBaru::whereIn('username', ['f12d_guru_a', 'f12d_guru_b'])->pluck('id');
                $tids = Teacher::whereIn('user_id', $uids)->pluck('id');
                DutySchedule::whereIn('teacher_id', $tids)->where('day', 'senin')->delete();
                DutySchedule::whereIn('teacher_id', $tids)->where('day', 'selasa')->delete();
                Teacher::whereIn('user_id', $uids)->delete();
                UserBaru::whereIn('username', ['f12d_guru_a', 'f12d_guru_b'])->delete();
                UserBaru::whereIn('id', [$this->ids['admin'] ?? 0])->delete();
                UserBaru::whereIn('id', [$this->ids['siswa'] ?? 0])->delete();
                UserBaru::whereIn('id', [$this->ids['guru'] ?? 0])->delete();
                UserBaru::whereIn('id', [$this->ids['kepsek'] ?? 0])->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function admin(): UserBaru
    {
        return UserBaru::findOrFail($this->ids['admin']);
    }

    private function teacherId(string $username): int
    {
        return (int) Teacher::where('user_id', UserBaru::where('username', $username)->value('id'))->value('id');
    }

    public function test_access_control(): void
    {
        $this->actingAs($this->admin())->get('/admin/duty-schedules')->assertStatus(200)->assertSee('Manajemen Jadwal Guru Piket');
        $this->actingAs($this->admin())->get('/admin/duty-schedules/create')->assertStatus(200);
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/admin/duty-schedules', ['teacher_id' => 1, 'day' => 'senin'])->assertRedirect('/login');
    }

    public function test_cross_role_forbidden(): void
    {
        foreach (['siswa', 'guru', 'kepsek'] as $key) {
            $this->refreshApplication();
            $this->reconnect();
            $user = UserBaru::find($this->ids[$key]);
            if ($user) $this->actingAs($user)->get('/admin/duty-schedules')->assertStatus(403);
        }
    }

    public function test_create_duplicate_and_multi_guru(): void
    {
        $tA = $this->teacherId('f12d_guru_a');
        $tB = $this->teacherId('f12d_guru_b');
        // Valid
        $this->actingAs($this->admin())->post('/admin/duty-schedules', ['teacher_id' => $tA, 'day' => 'senin'])
            ->assertRedirect(route('admin.duty-schedules.index'));
        // Duplikat guru+hari ditolak
        $this->actingAs($this->admin())->post('/admin/duty-schedules', ['teacher_id' => $tA, 'day' => 'senin'])
            ->assertSessionHasErrors('day');
        // Guru sama hari beda boleh
        $this->actingAs($this->admin())->post('/admin/duty-schedules', ['teacher_id' => $tA, 'day' => 'selasa'])
            ->assertRedirect(route('admin.duty-schedules.index'));
        // Multi guru satu hari boleh
        $this->actingAs($this->admin())->post('/admin/duty-schedules', ['teacher_id' => $tB, 'day' => 'senin'])
            ->assertRedirect(route('admin.duty-schedules.index'));
        $this->assertEquals(2, DutySchedule::where('day', 'senin')->whereIn('teacher_id', [$tA, $tB])->count());
        // Validasi: teacher invalid, hari invalid/minggu
        $this->actingAs($this->admin())->post('/admin/duty-schedules', ['teacher_id' => 999999, 'day' => 'senin'])->assertSessionHasErrors('teacher_id');
        $this->actingAs($this->admin())->post('/admin/duty-schedules', ['teacher_id' => $tA, 'day' => 'minggu'])->assertSessionHasErrors('day');
        $this->actingAs($this->admin())->post('/admin/duty-schedules', ['teacher_id' => $tA, 'day' => 'senin2'])->assertSessionHasErrors('day');
    }

    public function test_edit_and_delete(): void
    {
        $tA = $this->teacherId('f12d_guru_a');
        $tB = $this->teacherId('f12d_guru_b');
        $sched = DutySchedule::create(['teacher_id' => $tA, 'day' => 'senin']);
        // Edit valid: ganti guru
        $this->actingAs($this->admin())->put('/admin/duty-schedules/'.$sched->id, ['teacher_id' => $tB, 'day' => 'senin'])
            ->assertRedirect(route('admin.duty-schedules.index'));
        $this->assertEquals($tB, (int) $sched->fresh()->teacher_id);
        // Edit menjadi duplikat ditolak
        DutySchedule::create(['teacher_id' => $tA, 'day' => 'selasa']);
        $this->actingAs($this->admin())->put('/admin/duty-schedules/'.$sched->id, ['teacher_id' => $tA, 'day' => 'selasa'])
            ->assertSessionHasErrors('day');
        $this->assertEquals($tB, (int) $sched->fresh()->teacher_id);
        // Edit diri sendiri (nilai sama) boleh
        $this->actingAs($this->admin())->put('/admin/duty-schedules/'.$sched->id, ['teacher_id' => $tB, 'day' => 'senin'])
            ->assertRedirect(route('admin.duty-schedules.index'));
        // Delete: hanya jadwal terhapus, guru+user tetap
        $this->actingAs($this->admin())->delete('/admin/duty-schedules/'.$sched->id)
            ->assertRedirect(route('admin.duty-schedules.index'));
        $this->assertNull(DutySchedule::find($sched->id));
        $this->assertNotNull(Teacher::find($tB));
        $this->assertNotNull(UserBaru::where('username', 'f12d_guru_b')->first());
        DutySchedule::where('teacher_id', $tA)->where('day', 'selasa')->delete();
    }

    public function test_guru_duty_service_integration(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));
        $today = GuruDutyService::todayDay();
        $this->assertNotNull($today);
        $tA = $this->teacherId('f12d_guru_a');
        $teacher = Teacher::findOrFail($tA);
        $this->assertFalse(GuruDutyService::isOnDutyDay($teacher));
        $this->actingAs($this->admin())->post('/admin/duty-schedules', ['teacher_id' => $tA, 'day' => $today]);
        $this->assertTrue(GuruDutyService::isOnDutyDay($teacher->fresh()));
        $this->assertTrue(GuruDutyService::canClaim($teacher->fresh())['ok']);
        DutySchedule::where('teacher_id', $tA)->where('day', $today)->delete();
        Carbon::setTestNow(null);
    }
}




