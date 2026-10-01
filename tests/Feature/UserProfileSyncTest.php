<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProfileSyncTest extends TestCase
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
        $admin = UserBaru::where('username', 'fsy_admin')->first()
            ?: UserBaru::create(['name' => 'Admin FSY', 'username' => 'fsy_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);
        $this->ids = ['admin' => $admin->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                foreach (['fsy_guru', 'fsy_siswa', 'fsy_guru2', 'fsy_admin2'] as $u) {
                    $user = UserBaru::where('username', $u)->first();
                    if ($user) {
                        Teacher::where('user_id', $user->id)->delete();
                        Student::where('user_id', $user->id)->delete();
                        $user->delete();
                    }
                }
                UserBaru::whereKey($this->ids['admin'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function admin(): UserBaru
    {
        return UserBaru::findOrFail($this->ids['admin']);
    }

    private function payload(string $username, string $role): array
    {
        return [
            'name' => 'Nama '.$username, 'username' => $username,
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => $role, 'status' => 'aktif',
        ];
    }

    public function test_create_guru_appears_in_teachers(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->payload('fsy_guru', 'guru'))->assertRedirect();
        $user = UserBaru::where('username', 'fsy_guru')->firstOrFail();
        $teacher = Teacher::where('user_id', $user->id)->first();
        $this->assertNotNull($teacher);
        $this->assertNull(Student::where('user_id', $user->id)->first());
        $found = false;
        for ($page = 1; $page <= 5; $page++) {
            $res = $this->actingAs($this->admin())->get('/admin/teachers?page='.$page);
            $res->assertStatus(200);
            if (str_contains($res->getContent(), 'fsy_guru')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function test_create_siswa_appears_in_students(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->payload('fsy_siswa', 'siswa'))->assertRedirect();
        $user = UserBaru::where('username', 'fsy_siswa')->firstOrFail();
        $student = Student::where('user_id', $user->id)->first();
        $this->assertNotNull($student);
        $this->assertNull(Teacher::where('user_id', $user->id)->first());
        $found = false;
        for ($page = 1; $page <= 5; $page++) {
            $res = $this->actingAs($this->admin())->get('/admin/students?page='.$page);
            $res->assertStatus(200);
            if (str_contains($res->getContent(), 'fsy_siswa')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function test_admin_creates_no_profile_and_role_isolation(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->payload('fsy_admin2', 'admin'))->assertRedirect();
        $user = UserBaru::where('username', 'fsy_admin2')->firstOrFail();
        $this->assertNull(Teacher::where('user_id', $user->id)->first());
        $this->assertNull(Student::where('user_id', $user->id)->first());
    }

    public function test_edit_role_moves_profile(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->payload('fsy_guru2', 'guru'))->assertRedirect();
        $user = UserBaru::where('username', 'fsy_guru2')->firstOrFail();
        $this->assertNotNull(Teacher::where('user_id', $user->id)->first());
        $this->actingAs($this->admin())->put('/admin/users/'.$user->id, [
            'name' => 'X', 'username' => 'fsy_guru2', 'role' => 'siswa', 'status' => 'aktif',
        ])->assertRedirect();
        $user->refresh();
        $this->assertEquals('siswa', $user->role);
        $this->assertNull(Teacher::where('user_id', $user->id)->first());
        $this->assertNotNull(Student::where('user_id', $user->id)->first());
        $this->assertEquals(1, Student::where('user_id', $user->id)->count());
    }

    public function test_delete_user_removes_orphan_profile(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->payload('fsy_siswa', 'siswa'))->assertRedirect();
        $user = UserBaru::where('username', 'fsy_siswa')->firstOrFail();
        $this->actingAs($this->admin())->delete('/admin/users/'.$user->id)->assertRedirect();
        $this->assertNull(UserBaru::where('username', 'fsy_siswa')->first());
        $this->assertEquals(0, Student::where('user_id', $user->id)->count());
        $this->actingAs($this->admin())->get('/admin/students')->assertDontSee('fsy_siswa', false);
    }
}
