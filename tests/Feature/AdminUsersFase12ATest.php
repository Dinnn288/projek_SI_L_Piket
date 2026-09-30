<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUsersFase12ATest extends TestCase
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

        $admin = UserBaru::where('username', 'f12a_admin')->first()
            ?: UserBaru::create(['name' => 'Admin F12A', 'username' => 'f12a_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);
        $siswa = UserBaru::where('username', 'f12a_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa F12A', 'username' => 'f12a_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        $guru = UserBaru::where('username', 'f12a_guru')->first()
            ?: UserBaru::create(['name' => 'Guru F12A', 'username' => 'f12a_guru', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        $kepsek = UserBaru::where('username', 'f12a_kepsek')->first()
            ?: UserBaru::create(['name' => 'Kepsek F12A', 'username' => 'f12a_kepsek', 'password' => Hash::make('password123'), 'role' => 'kepala_sekolah', 'status' => 'aktif']);

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
                // Hapus user uji yang dibuat test ini by ID eksplisit.
                $created = UserBaru::where('username', 'like', 'f12a\_created\_%')->pluck('id');
                foreach ($created as $id) {
                    UserBaru::whereKey($id)->delete();
                }
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

    public function test_access_control(): void
    {
        $this->actingAs($this->admin())->get('/admin/users')->assertStatus(200)->assertSee('Manajemen Users');
        $this->actingAs($this->admin())->get('/admin/users/create')->assertStatus(200);
        $this->refreshApplication();
        $this->reconnect();
        $this->get('/admin/users')->assertRedirect('/login');
        $this->post('/admin/users', ['username' => 'x'])->assertRedirect('/login');
    }

    public function test_cross_role_forbidden(): void
    {
        $siswa = UserBaru::find($this->ids['siswa']);
        $guru = UserBaru::find($this->ids['guru']);
        $kepsek = UserBaru::find($this->ids['kepsek']);
        if ($siswa) $this->actingAs($siswa)->get('/admin/users')->assertStatus(403);
        if ($guru) {
            $this->refreshApplication();
            $this->reconnect();
            $this->actingAs($guru)->get('/admin/users')->assertStatus(403);
        }
        if ($kepsek) {
            $this->refreshApplication();
            $this->reconnect();
            $this->actingAs($kepsek)->get('/admin/users')->assertStatus(403);
        }
    }

    public function test_create_validation_and_hash(): void
    {
        // Duplicate role/status invalid
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'X', 'username' => 'f12a_created_1', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'invalid', 'status' => 'aktif',
        ])->assertSessionHasErrors('role');
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'X', 'username' => 'f12a_created_1', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'siswa', 'status' => 'invalid',
        ])->assertSessionHasErrors('status');
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'X', 'username' => 'f12a_created_1', 'password' => 'password123',
            'password_confirmation' => 'beda', 'role' => 'siswa', 'status' => 'aktif',
        ])->assertSessionHasErrors('password');

        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'User Baru F12A', 'username' => 'f12a_created_1', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'siswa', 'status' => 'aktif',
        ])->assertRedirect(route('admin.users.index'));
        $created = UserBaru::where('username', 'f12a_created_1')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $created->password));
        $this->assertNotEquals('password123', $created->password);
        // Duplicate ditolak
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Dup', 'username' => 'f12a_created_1', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'siswa', 'status' => 'aktif',
        ])->assertSessionHasErrors('username');
        // Login user baru berhasil (aktif)
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'f12a_created_1', 'password' => 'password123'])->assertRedirect('/siswa');
    }

    public function test_edit_password_optional_and_unique(): void
    {
        $created = UserBaru::create(['name' => 'Edit F12A', 'username' => 'f12a_created_2', 'password' => Hash::make('lama12345'), 'role' => 'guru', 'status' => 'aktif']);
        $oldHash = $created->password;
        // Password kosong -> hash lama bertahan
        $this->actingAs($this->admin())->put('/admin/users/'.$created->id, [
            'name' => 'Edit F12A Updated', 'username' => 'f12a_created_2',
            'password' => '', 'password_confirmation' => '',
            'role' => 'guru', 'status' => 'aktif',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertEquals($oldHash, $created->fresh()->password);
        $this->assertTrue(Hash::check('lama12345', $created->fresh()->password));
        // Password baru -> di-hash
        $this->actingAs($this->admin())->put('/admin/users/'.$created->id, [
            'name' => 'Edit F12A Updated', 'username' => 'f12a_created_2',
            'password' => 'baru12345', 'password_confirmation' => 'baru12345',
            'role' => 'guru', 'status' => 'aktif',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertTrue(Hash::check('baru12345', $created->fresh()->password));
        // Username milik sendiri boleh; milik akun lain ditolak
        $this->actingAs($this->admin())->put('/admin/users/'.$created->id, [
            'name' => 'X', 'username' => 'f12a_created_2',
            'role' => 'guru', 'status' => 'aktif',
        ])->assertRedirect(route('admin.users.index'));
        $this->actingAs($this->admin())->put('/admin/users/'.$created->id, [
            'name' => 'X', 'username' => 'f12a_admin',
            'role' => 'guru', 'status' => 'aktif',
        ])->assertSessionHasErrors('username');
    }

    public function test_status_login_and_self_delete_protection(): void
    {
        $created = UserBaru::create(['name' => 'Status F12A', 'username' => 'f12a_created_3', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'nonaktif']);
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'f12a_created_3', 'password' => 'password123']);
        $this->assertGuest();
        // Aktifkan -> bisa login
        $admin = UserBaru::where('username', 'f12a_admin')->firstOrFail();
        $this->actingAs($admin)->put('/admin/users/'.$created->id, [
            'name' => 'Status F12A', 'username' => 'f12a_created_3',
            'role' => 'siswa', 'status' => 'aktif',
        ]);
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'f12a_created_3', 'password' => 'password123'])->assertRedirect('/siswa');
        // Admin tidak bisa hapus dirinya sendiri
        $admin = UserBaru::where('username', 'f12a_admin')->firstOrFail();
        $this->actingAs($admin)->delete('/admin/users/'.$admin->id)->assertSessionHas('error');
        $this->assertNotNull(UserBaru::find($admin->id));
    }

    public function test_safe_delete_relation_protection(): void
    {
        // User dengan relasi student tidak bisa dihapus
        $siswaUser = UserBaru::where('username', 'f12a_siswa')->firstOrFail();
        $classId = \App\Models\SchoolClass::value('id');
        if ($classId && ! Student::where('user_id', $siswaUser->id)->exists()) {
            Student::create(['user_id' => $siswaUser->id, 'nis' => 'F12ANIS001', 'class_id' => $classId]);
            $createdStudent = true;
        } else {
            $createdStudent = false;
        }
        $this->actingAs($this->admin())->delete('/admin/users/'.$siswaUser->id)->assertSessionHas('error');
        $this->assertNotNull(UserBaru::find($siswaUser->id));
        if (! empty($createdStudent)) {
            Student::where('user_id', $siswaUser->id)->delete();
        }
        // User tanpa relasi bisa dihapus
        $plain = UserBaru::create(['name' => 'Plain F12A', 'username' => 'f12a_created_4', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        $this->actingAs($this->admin())->delete('/admin/users/'.$plain->id)->assertRedirect(route('admin.users.index'));
        $this->assertNull(UserBaru::find($plain->id));
        // Password tidak tampil di list
        $this->actingAs($this->admin())->get('/admin/users')->assertDontSee('password123');
    }

    public function test_security_roles_and_mass_assignment(): void
    {
        $siswa = UserBaru::where('username', 'f12a_siswa')->first();
        $guru = UserBaru::where('username', 'f12a_guru')->first();
        $kepsek = UserBaru::where('username', 'f12a_kepsek')->first();
        if ($siswa) $this->actingAs($siswa)->post('/admin/users', ['username' => 'x'])->assertStatus(403);
        if ($guru) $this->actingAs($guru)->post('/admin/users', ['username' => 'x'])->assertStatus(403);
        if ($kepsek) $this->actingAs($kepsek)->post('/admin/users', ['username' => 'x'])->assertStatus(403);
    }
}




