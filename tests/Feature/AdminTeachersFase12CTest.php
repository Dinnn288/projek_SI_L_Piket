<?php

namespace Tests\Feature;

use App\Models\DutySchedule;
use App\Models\Report;
use App\Models\ReportCategory;
use App\Models\ReportHistory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTeachersFase12CTest extends TestCase
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
        $admin = $mkUser('f12c_admin', 'Admin F12C', 'admin');
        $siswa = $mkUser('f12c_siswa', 'Siswa F12C', 'siswa');
        $guru = $mkUser('f12c_guru', 'Guru F12C', 'guru');
        $kepsek = $mkUser('f12c_kepsek', 'Kepsek F12C', 'kepala_sekolah');

        $this->ids = [
            'admin' => $admin->id, 'siswa' => $siswa->id,
            'guru' => $guru->id, 'kepsek' => $kepsek->id,
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                // Cleanup eksplisit: teacher uji by username, student uji, user uji.
                $usernames = ['f12c_created_1', 'f12c_created_2', 'f12c_created_3', 'f12c_created_4', 'f12c_created_5', 'f12c_siswa_t'];
                $uids = UserBaru::whereIn('username', $usernames)->pluck('id');
                $teacherIds = Teacher::whereIn('user_id', $uids)->pluck('id');
                if ($teacherIds->isNotEmpty()) {
                    DutySchedule::whereIn('teacher_id', $teacherIds)->delete();
                    $repIds = Report::whereIn('assigned_teacher_id', $teacherIds)->pluck('id');
                    Report::whereIn('assigned_teacher_id', $teacherIds)->update(['assigned_teacher_id' => null]);
                    ReportHistory::where('action', 'claimed')->whereIn('user_id', $uids)->delete();
                    ReportHistory::whereIn('report_id', $repIds)->where('action', 'claimed')->delete();
                }
                Teacher::whereIn('user_id', $uids)->delete();
                $sids = Student::whereIn('user_id', $uids)->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    \App\Models\ReportAttachment::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::whereIn('user_id', $uids)->delete();
                UserBaru::whereIn('id', $uids)->delete();
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

    private function basePayload(string $username, string $nip): array
    {
        return [
            'name' => 'Nama '.$username,
            'username' => $username,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'aktif',
            'nip' => $nip,
            'phone' => '081',
        ];
    }

    public function test_access_control(): void
    {
        $this->actingAs($this->admin())->get('/admin/teachers')->assertStatus(200)->assertSee('Manajemen Guru');
        $this->actingAs($this->admin())->get('/admin/teachers/create')->assertStatus(200);
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/admin/teachers', ['username' => 'x'])->assertRedirect('/login');
    }

    public function test_cross_role_forbidden(): void
    {
        foreach (['siswa', 'guru', 'kepsek'] as $key) {
            $this->refreshApplication();
            $this->reconnect();
            $user = UserBaru::find($this->ids[$key]);
            if ($user) $this->actingAs($user)->get('/admin/teachers')->assertStatus(403);
        }
    }

    public function test_create_teacher_with_account(): void
    {
        $this->actingAs($this->admin())->post('/admin/teachers', $this->basePayload('f12c_created_1', 'F12CNIP001'))
            ->assertRedirect(route('admin.teachers.index'));
        $user = UserBaru::where('username', 'f12c_created_1')->firstOrFail();
        $this->assertEquals('guru', $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
        $teacher = Teacher::where('user_id', $user->id)->firstOrFail();
        $this->assertEquals('F12CNIP001', $teacher->nip);
        // Validasi: username duplikat, konfirmasi beda
        $dup = $this->basePayload('f12c_created_1', 'F12CNIP002');
        $this->actingAs($this->admin())->post('/admin/teachers', $dup)->assertSessionHasErrors('username');
        $badPass = $this->basePayload('f12c_created_2', 'F12CNIP002');
        $badPass['password_confirmation'] = 'beda';
        $this->actingAs($this->admin())->post('/admin/teachers', $badPass)->assertSessionHasErrors('password');
        // Login guru baru berhasil
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'f12c_created_1', 'password' => 'password123'])->assertRedirect('/guru');
    }

    public function test_edit_teacher_and_account(): void
    {
        $this->actingAs($this->admin())->post('/admin/teachers', $this->basePayload('f12c_created_2', 'F12CNIP002'));
        $teacher = Teacher::where('nip', 'F12CNIP002')->firstOrFail();
        $user = $teacher->userBaru;
        $oldHash = $user->password;
        // Password kosong -> hash lama bertahan
        $this->actingAs($this->admin())->put('/admin/teachers/'.$teacher->id, [
            'name' => 'Nama Updated', 'username' => 'f12c_created_2',
            'password' => '', 'password_confirmation' => '',
            'status' => 'nonaktif', 'nip' => 'F12CNIP002U', 'phone' => '082',
        ])->assertRedirect(route('admin.teachers.index'));
        $teacher->refresh();
        $user->refresh();
        $this->assertEquals('F12CNIP002U', $teacher->nip);
        $this->assertEquals($oldHash, $user->password);
        $this->assertEquals('nonaktif', $user->status);
        // Password baru di-hash
        $this->actingAs($this->admin())->put('/admin/teachers/'.$teacher->id, [
            'name' => 'Nama Updated', 'username' => 'f12c_created_2',
            'password' => 'baru12345', 'password_confirmation' => 'baru12345',
            'status' => 'aktif', 'nip' => 'F12CNIP002U',
        ])->assertRedirect(route('admin.teachers.index'));
        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));
    }

    public function test_status_login_and_safe_delete(): void
    {
        $this->actingAs($this->admin())->post('/admin/teachers', $this->basePayload('f12c_created_3', 'F12CNIP003'));
        $teacher = Teacher::where('nip', 'F12CNIP003')->firstOrFail();
        // Nonaktif -> login gagal
        $this->actingAs($this->admin())->put('/admin/teachers/'.$teacher->id, [
            'name' => 'X', 'username' => 'f12c_created_3', 'status' => 'nonaktif', 'nip' => 'F12CNIP003',
        ]);
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'f12c_created_3', 'password' => 'password123']);
        $this->assertGuest();
        // Guru dengan jadwal -> delete ditolak
        $teacher = Teacher::where('nip', 'F12CNIP003')->firstOrFail();
        DutySchedule::create(['teacher_id' => $teacher->id, 'day' => 'senin']);
        $admin = UserBaru::where('username', 'f12c_admin')->firstOrFail();
        $this->actingAs($admin)->delete('/admin/teachers/'.$teacher->id)->assertSessionHas('error');
        $this->assertNotNull(Teacher::find($teacher->id));
        // Hapus jadwal -> guru dengan history claim ditolak (bila ada report existing)
        DutySchedule::where('teacher_id', $teacher->id)->delete();
        $adminUser = UserBaru::where('username', 'f12c_admin')->firstOrFail();
        $realReport = Report::first();
        if ($realReport) {
            ReportHistory::create([
                'report_id' => $realReport->id, 'user_id' => $teacher->userBaru->id,
                'action' => 'claimed', 'from_status' => 'menunggu',
                'to_status' => 'dalam_penanganan', 'note' => null,
            ]);
            $this->actingAs($adminUser)->delete('/admin/teachers/'.$teacher->id)->assertSessionHas('error');
            $this->assertNotNull(Teacher::find($teacher->id));
            ReportHistory::where('report_id', $realReport->id)->where('user_id', $teacher->userBaru->id)->where('action', 'claimed')->delete();
        }
        // Tanpa relasi -> delete berhasil (teacher + akun ikut terhapus)
        $this->actingAs($adminUser)->delete('/admin/teachers/'.$teacher->id)->assertRedirect(route('admin.teachers.index'));
        $this->assertNull(Teacher::find($teacher->id));
        $this->assertNull(UserBaru::where('username', 'f12c_created_3')->first());
        // Password tidak tampil di list
        $this->actingAs($adminUser)->get('/admin/teachers')->assertDontSee('password123');
    }

    public function test_active_report_blocks_delete(): void
    {
        $this->actingAs($this->admin())->post('/admin/teachers', $this->basePayload('f12c_created_5', 'F12CNIP005'));
        $teacher = Teacher::where('nip', 'F12CNIP005')->firstOrFail();
        $student = Student::first();
        $catId = (int) ReportCategory::value('id');
        if ($student && $catId) {
            $report = Report::create([
                'student_id' => $student->id, 'class_id' => $student->class_id, 'category_id' => $catId,
                'title' => 'T-F12C', 'description' => 'd', 'location' => 'l',
                'priority' => 'rendah', 'status' => 'dalam_penanganan',
                'assigned_teacher_id' => $teacher->id, 'assigned_at' => now(),
            ]);
            $admin = UserBaru::where('username', 'f12c_admin')->firstOrFail();
            $this->actingAs($admin)->delete('/admin/teachers/'.$teacher->id)->assertSessionHas('error');
            $this->assertNotNull(Teacher::find($teacher->id));
            ReportHistory::where('report_id', $report->id)->delete();
            \App\Models\ReportAttachment::where('report_id', $report->id)->delete();
            \App\Models\Notification::where('report_id', $report->id)->delete();
            $report->delete();
        }
        $admin = UserBaru::where('username', 'f12c_admin')->firstOrFail();
        $this->actingAs($admin)->delete('/admin/teachers/'.$teacher->id)->assertRedirect(route('admin.teachers.index'));
        $this->assertNull(Teacher::find($teacher->id));
    }

    public function test_guru_workflow_still_works(): void
    {
        $this->actingAs($this->admin())->post('/admin/teachers', $this->basePayload('f12c_created_4', 'F12CNIP004'));
        $guru = UserBaru::where('username', 'f12c_created_4')->firstOrFail();
        $this->actingAs($guru)->get('/guru')->assertStatus(200);
        $this->actingAs($guru)->get('/guru/laporan')->assertStatus(200);
    }
}




