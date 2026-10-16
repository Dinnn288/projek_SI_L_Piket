<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportCategory;
use App\Models\ReportHistory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminStudentsFase12BTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Storage::fake('local');

        $admin = UserBaru::where('username', 'f12b_admin')->first()
            ?: UserBaru::create(['name' => 'Admin F12B', 'username' => 'f12b_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);
        $siswa = UserBaru::where('username', 'f12b_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa F12B', 'username' => 'f12b_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        $guru = UserBaru::where('username', 'f12b_guru')->first()
            ?: UserBaru::create(['name' => 'Guru F12B', 'username' => 'f12b_guru', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        $kepsek = UserBaru::where('username', 'f12b_kepsek')->first()
            ?: UserBaru::create(['name' => 'Kepsek F12B', 'username' => 'f12b_kepsek', 'password' => Hash::make('password123'), 'role' => 'kepala_sekolah', 'status' => 'aktif']);

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
                // Cleanup eksplisit by ID: reports milik student uji, lalu student+user uji.
                $usernames = ['f12b_created_1', 'f12b_created_2', 'f12b_created_3', 'f12b_created_4', 'f12b_created_5'];
                $uids = UserBaru::whereIn('username', $usernames)->pluck('id');
                $sids = Student::whereIn('user_id', $uids)->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
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

    private function classId(): int
    {
        return (int) SchoolClass::value('id');
    }

    private function basePayload(string $username, string $nis): array
    {
        return [
            'name' => 'Nama '.$username,
            'username' => $username,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'aktif',
            'nis' => $nis,
            'class_id' => $this->classId(),
            'phone' => '081',
        ];
    }

    public function test_access_control(): void
    {
        $this->actingAs($this->admin())->get('/admin/students')->assertStatus(200)->assertSee('Manajemen Siswa');
        $this->actingAs($this->admin())->get('/admin/students/create')->assertStatus(200);
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/admin/students', ['username' => 'x'])->assertRedirect('/login');
    }

    public function test_cross_role_forbidden(): void
    {
        foreach (['siswa', 'guru', 'kepsek'] as $key) {
            $this->refreshApplication();
            $this->reconnect();
            $user = UserBaru::find($this->ids[$key]);
            if ($user) $this->actingAs($user)->get('/admin/students')->assertStatus(403);
        }
    }

    public function test_create_student_with_account(): void
    {
        $this->actingAs($this->admin())->post('/admin/students', $this->basePayload('f12b_created_1', 'F12BNIS001'))
            ->assertRedirect(route('admin.students.index'));
        $user = UserBaru::where('username', 'f12b_created_1')->firstOrFail();
        $this->assertEquals('siswa', $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
        $student = Student::where('user_id', $user->id)->firstOrFail();
        $this->assertEquals('F12BNIS001', $student->nis);
        $this->assertEquals($this->classId(), (int) $student->class_id);
        // Validasi: nis duplikat, username duplikat, class invalid, konfirmasi beda
        $dup = $this->basePayload('f12b_created_2', 'F12BNIS001');
        $this->actingAs($this->admin())->post('/admin/students', $dup)->assertSessionHasErrors('nis');
        $dupUser = $this->basePayload('f12b_created_1', 'F12BNIS002');
        $this->actingAs($this->admin())->post('/admin/students', $dupUser)->assertSessionHasErrors('username');
        $badClass = $this->basePayload('f12b_created_2', 'F12BNIS002');
        $badClass['class_id'] = 999999;
        $this->actingAs($this->admin())->post('/admin/students', $badClass)->assertSessionHasErrors('class_id');
        $badPass = $this->basePayload('f12b_created_2', 'F12BNIS002');
        $badPass['password_confirmation'] = 'beda';
        $this->actingAs($this->admin())->post('/admin/students', $badPass)->assertSessionHasErrors('password');
        // Login siswa baru berhasil
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'f12b_created_1', 'password' => 'password123'])->assertRedirect('/siswa');
    }

    public function test_edit_student_and_account(): void
    {
        $this->actingAs($this->admin())->post('/admin/students', $this->basePayload('f12b_created_3', 'F12BNIS003'));
        $student = Student::where('nis', 'F12BNIS003')->firstOrFail();
        $user = $student->userBaru;
        $oldHash = $user->password;
        $otherClass = (int) SchoolClass::where('id', '!=', $student->class_id)->value('id');
        $payload = [
            'name' => 'Nama Updated', 'username' => 'f12b_created_3',
            'password' => '', 'password_confirmation' => '',
            'status' => 'nonaktif', 'nis' => 'F12BNIS003U',
            'class_id' => $otherClass ?: $student->class_id, 'phone' => '082',
        ];
        $this->actingAs($this->admin())->put('/admin/students/'.$student->id, $payload)
            ->assertRedirect(route('admin.students.index'));
        $student->refresh();
        $user->refresh();
        $this->assertEquals('F12BNIS003U', $student->nis);
        $this->assertEquals($oldHash, $user->password);
        $this->assertEquals('nonaktif', $user->status);
        // Password baru di-hash
        $payload['password'] = 'baru12345';
        $payload['password_confirmation'] = 'baru12345';
        $this->actingAs($this->admin())->put('/admin/students/'.$student->id, $payload)
            ->assertRedirect(route('admin.students.index'));
        $this->assertTrue(Hash::check('baru12345', $user->fresh()->password));
    }

    public function test_status_login_and_safe_delete(): void
    {
        $this->actingAs($this->admin())->post('/admin/students', $this->basePayload('f12b_created_4', 'F12BNIS004'));
        $student = Student::where('nis', 'F12BNIS004')->firstOrFail();
        // Nonaktif -> login gagal
        $this->actingAs($this->admin())->put('/admin/students/'.$student->id, [
            'name' => 'X', 'username' => 'f12b_created_4', 'status' => 'nonaktif',
            'nis' => 'F12BNIS004', 'class_id' => $student->class_id,
        ]);
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'f12b_created_4', 'password' => 'password123']);
        $this->assertGuest();
        // Buat report untuk student -> delete ditolak
        $student = Student::where('nis', 'F12BNIS004')->firstOrFail();
        $catId = (int) ReportCategory::value('id');
        $report = Report::create([
            'student_id' => $student->id, 'class_id' => $student->class_id, 'category_id' => $catId,
            'title' => 'T', 'description' => 'd', 'location' => 'l',
            'priority' => 'rendah', 'status' => 'menunggu',
        ]);
        $admin = UserBaru::where('username', 'f12b_admin')->firstOrFail();
        $this->actingAs($admin)->delete('/admin/students/'.$student->id)->assertSessionHas('error');
        $this->assertNotNull(Student::find($student->id));
        $this->assertNotNull(UserBaru::where('username', 'f12b_created_4')->first());
        // Hapus report -> delete berhasil (student + akun ikut terhapus)
        ReportHistory::where('report_id', $report->id)->delete();
        ReportAttachment::where('report_id', $report->id)->delete();
        $report->delete();
        $this->actingAs($admin)->delete('/admin/students/'.$student->id)->assertRedirect(route('admin.students.index'));
        $this->assertNull(Student::find($student->id));
        $this->assertNull(UserBaru::where('username', 'f12b_created_4')->first());
        // Password tidak tampil di list
        $this->actingAs($admin)->get('/admin/students')->assertDontSee('password123');
    }

    public function test_student_can_still_create_report(): void
    {
        $this->actingAs($this->admin())->post('/admin/students', $this->basePayload('f12b_created_5', 'F12BNIS005'));
        $siswa = UserBaru::where('username', 'f12b_created_5')->firstOrFail();
        $this->actingAs($siswa)->get('/siswa')->assertStatus(200);
        $this->actingAs($siswa)->get('/siswa/laporan/create')->assertStatus(200);
    }
}




