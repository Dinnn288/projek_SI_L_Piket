<?php

namespace Tests\Feature;

use App\Models\DutySchedule;
use App\Models\Major;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportCategory;
use App\Models\ReportHistory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Services\GuruDutyService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMasterDataFase12FTest extends TestCase
{
    private array $ids = [];

    private function reconnect(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket_test']);
        DB::reconnect('mysql');
        \Tests\TestDatabaseGuard::assertTestDatabaseSafe();
    }

    private function makePhoto(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $tmp = tempnam(sys_get_temp_dir(), 'f12f').'.png';
        file_put_contents($tmp, $png);
        clearstatcache(true, $tmp);

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $admin = UserBaru::where('username', 'f12f_admin')->first()
            ?: UserBaru::create(['name' => 'Admin F12F', 'username' => 'f12f_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);
        foreach ([
            ['f12f_siswa', 'Siswa F12F', 'siswa'],
            ['f12f_guru', 'Guru F12F', 'guru'],
            ['f12f_kepsek', 'Kepsek F12F', 'kepala_sekolah'],
        ] as [$u, $n, $r]) {
            if (! UserBaru::where('username', $u)->exists()) {
                UserBaru::create(['name' => $n, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $r, 'status' => 'aktif']);
            }
        }

        $this->ids = ['admin' => $admin->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                // Cleanup eksplisit by nama/ID uji Fase 12F.
                $sids = Student::where('nis', 'F12FNIS001')->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::where('nis', 'F12FNIS001')->delete();
                $uids = UserBaru::whereIn('username', ['f12f_siswa_t', 'f12f_guru_t'])->pluck('id');
                $tids = Teacher::whereIn('user_id', $uids)->pluck('id');
                if ($tids->isNotEmpty()) {
                    DutySchedule::whereIn('teacher_id', $tids)->delete();
                    Report::whereIn('assigned_teacher_id', $tids)->update(['assigned_teacher_id' => null]);
                }
                Teacher::whereIn('user_id', $uids)->delete();
                UserBaru::whereIn('username', ['f12f_siswa_t', 'f12f_guru_t'])->delete();
                SchoolClass::where('name', 'F12F_Kelas')->delete();
                Major::where('name', 'F12F_Jurusan')->delete();
                ReportCategory::where('name', 'F12F_Kategori')->delete();
                foreach (['f12f_admin', 'f12f_siswa', 'f12f_guru', 'f12f_kepsek'] as $u) {
                    UserBaru::where('username', $u)->delete();
                }
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

    public function test_admin_pages_accessible(): void
    {
        $admin = $this->admin();
        foreach ([
            '/admin', '/admin/users', '/admin/users/create',
            '/admin/students', '/admin/students/create',
            '/admin/teachers', '/admin/teachers/create',
            '/admin/majors', '/admin/majors/create',
            '/admin/classes', '/admin/classes/create',
            '/admin/duty-schedules', '/admin/duty-schedules/create',
            '/admin/report-categories', '/admin/report-categories/create',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertStatus(200);
        }
    }

    public function test_cross_role_all_admin_pages_forbidden(): void
    {
        foreach (['f12f_siswa', 'f12f_guru', 'f12f_kepsek'] as $u) {
            $this->refreshApplication();
            $this->reconnect();
            $user = UserBaru::where('username', $u)->first();
            if (! $user) continue;
            foreach (['/admin', '/admin/users', '/admin/students', '/admin/teachers', '/admin/majors', '/admin/classes', '/admin/duty-schedules', '/admin/report-categories'] as $url) {
                $this->actingAs($user)->get($url)->assertStatus(403);
            }
        }
    }

    public function test_navigation_links_valid(): void
    {
        $res = $this->actingAs($this->admin())->get('/admin');
        $res->assertStatus(200);
        foreach (['/admin/users', '/admin/majors', '/admin/classes', '/admin/students', '/admin/teachers', '/admin/duty-schedules', '/admin/report-categories'] as $url) {
            $res->assertSee($url, false);
        }
    }

    public function test_full_business_flow(): void
    {
        $admin = $this->admin();
        // Major -> Class
        $this->actingAs($admin)->post('/admin/majors', ['name' => 'F12F_Jurusan', 'status' => 'aktif'])->assertRedirect();
        $major = Major::where('name', 'F12F_Jurusan')->firstOrFail();
        $this->actingAs($admin)->post('/admin/classes', ['name' => 'F12F_Kelas', 'major_id' => $major->id, 'status' => 'aktif'])->assertRedirect();
        $class = SchoolClass::where('name', 'F12F_Kelas')->firstOrFail();
        $this->assertEquals($major->id, (int) $class->major_id);
        // Student + account
        $this->actingAs($admin)->post('/admin/students', [
            'name' => 'Siswa T', 'username' => 'f12f_siswa_t', 'password' => 'password123',
            'password_confirmation' => 'password123', 'status' => 'aktif',
            'nis' => 'F12FNIS001', 'class_id' => $class->id,
        ])->assertRedirect();
        $siswaUser = UserBaru::where('username', 'f12f_siswa_t')->firstOrFail();
        $this->assertEquals('siswa', $siswaUser->role);
        $student = Student::where('user_id', $siswaUser->id)->firstOrFail();
        $this->assertEquals($class->id, (int) $student->class_id);
        // Teacher + account
        $this->actingAs($admin)->post('/admin/teachers', [
            'name' => 'Guru T', 'username' => 'f12f_guru_t', 'password' => 'password123',
            'password_confirmation' => 'password123', 'status' => 'aktif',
            'nip' => 'F12FNIP001',
        ])->assertRedirect();
        $guruUser = UserBaru::where('username', 'f12f_guru_t')->firstOrFail();
        $teacher = Teacher::where('user_id', $guruUser->id)->firstOrFail();
        // Schedule hari ini
        $today = GuruDutyService::todayDay();
        $this->assertNotNull($today);
        $this->actingAs($admin)->post('/admin/duty-schedules', ['teacher_id' => $teacher->id, 'day' => $today])->assertRedirect();
        $this->assertTrue(GuruDutyService::isOnDutyDay($teacher->fresh()));
        // Category
        $this->actingAs($admin)->post('/admin/report-categories', [
            'name' => 'F12F_Kategori', 'status' => 'aktif', 'priority' => 'tinggi',
        ])->assertRedirect();
        $cat = ReportCategory::where('name', 'F12F_Kategori')->firstOrFail();
        // Student create report (priority snapshot)
        $this->actingAs($siswaUser)->post('/siswa/laporan', [
            'title' => 'F12F Report', 'category_id' => $cat->id,
            'location' => 'Kelas - lokasi', 'description' => 'Deskripsi F12F',
            'photos' => [$this->makePhoto('a.png')],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();
        $report = Report::where('title', 'F12F Report')->firstOrFail();
        $this->assertEquals($student->id, (int) $report->student_id);
        $this->assertEquals($class->id, (int) $report->class_id);
        $this->assertEquals($cat->id, (int) $report->category_id);
        $this->assertEquals('tinggi', $report->priority);
        $this->assertEquals('menunggu', $report->status);
        // Priority snapshot: ubah kategori -> laporan tetap
        $this->actingAs($admin)->put('/admin/report-categories/'.$cat->id, [
            'name' => 'F12F_Kategori', 'status' => 'aktif', 'priority' => 'rendah',
        ]);
        $this->assertEquals('tinggi', $report->fresh()->priority);
        // Guru claim -> complete
        $this->actingAs($guruUser)->post('/guru/laporan/'.$report->id.'/claim')->assertSessionHas('success');
        $this->assertEquals($teacher->id, (int) $report->fresh()->assigned_teacher_id);
        $this->actingAs($guruUser)->post('/guru/laporan/'.$report->id.'/complete', ['note' => 'Selesai F12F.'])->assertSessionHas('success');
        $this->assertEquals('selesai', $report->fresh()->status);
        $this->assertNotNull(ReportHistory::where('report_id', $report->id)->where('action', 'completed')->first());
        $this->assertNotNull(Notification::where('report_id', $report->id)->where('title', 'Laporan Selesai')->first());
        // Safe delete: kategori/major/class/student/teacher terproteksi saat terpakai
        $this->actingAs($admin)->delete('/admin/report-categories/'.$cat->id)->assertSessionHas('error');
        $this->actingAs($admin)->delete('/admin/majors/'.$major->id)->assertSessionHas('error');
        $this->actingAs($admin)->delete('/admin/classes/'.$class->id)->assertSessionHas('error');
        // Legacy users untouched
        $this->assertEquals(0, \App\Models\User::count());
    }

    public function test_auth_login_logout_all_roles(): void
    {
        foreach ([['f12f_admin', '/admin'], ['f12f_siswa', '/siswa'], ['f12f_guru', '/guru'], ['f12f_kepsek', '/kepala-sekolah']] as [$u, $dest]) {
            $this->refreshApplication();
            $this->reconnect();
            $this->post('/login', ['username' => $u, 'password' => 'password123'])->assertRedirect($dest);
        }
    }
}




