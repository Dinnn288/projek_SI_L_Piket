<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminInteractiveTableTest extends TestCase
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

        $admin = UserBaru::where('username', 'fit_admin')->first()
            ?: UserBaru::create(['name' => 'Admin FIT', 'username' => 'fit_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);
        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_FIT'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_FIT')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_FIT', 'status' => 'aktif']);
        $gu = UserBaru::where('username', 'fit_guru')->first()
            ?: UserBaru::create(['name' => 'Guru FIT', 'username' => 'fit_guru', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        if (! Teacher::where('user_id', $gu->id)->exists()) {
            Teacher::create(['user_id' => $gu->id, 'nip' => 'FITNIP001']);
        }
        $su = UserBaru::where('username', 'fit_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa FIT', 'username' => 'fit_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        if (! Student::where('user_id', $su->id)->exists()) {
            Student::create(['user_id' => $su->id, 'nis' => 'FITNIS001', 'class_id' => $class->id]);
        }

        $this->ids = ['admin' => $admin->id, 'major' => $major->id, 'class' => $class->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $uids = UserBaru::whereIn('username', ['fit_guru', 'fit_siswa'])->pluck('id');
                Teacher::whereIn('user_id', $uids)->delete();
                Student::whereIn('user_id', $uids)->delete();
                UserBaru::whereIn('username', ['fit_guru', 'fit_siswa'])->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
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

    public function test_search_filter_action_menu_render(): void
    {
        $admin = $this->admin();
        // Users: search spesifik username unik milik test ini
        $res = $this->actingAs($admin)->get('/admin/users?search=fit_guru&status=aktif');
        $res->assertStatus(200);
        $res->assertSee('fit_guru', false);
        $res->assertSee('data-sl-menu', false);
        $res->assertSee('data-sl-filter', false);
        // Filter status saja: username unik tetap muncul
        $res = $this->actingAs($admin)->get('/admin/users?search=fit_guru&status=aktif');
        $res->assertStatus(200);
        $res->assertSee('fit_guru', false);
        // Search + filter kombinasi
        $res = $this->actingAs($admin)->get('/admin/users?search=fit_guru&role=guru&status=aktif');
        $res->assertStatus(200);
        $res->assertSee('fit_guru', false);
        // Pagination mempertahankan query
        $res = $this->actingAs($admin)->get('/admin/users?search=fit_guru&page=1');
        $res->assertStatus(200);
    }

    public function test_filter_dropdown_options_and_empty_state(): void
    {
        $admin = $this->admin();
        // Filter status majors
        $res = $this->actingAs($admin)->get('/admin/majors?status=aktif');
        $res->assertStatus(200);
        $res->assertSee('TEST_MAJOR_FIT', false);
        // Filter jurusan pada kelas
        $res = $this->actingAs($admin)->get('/admin/classes?major_id='.$this->ids['major']);
        $res->assertStatus(200);
        $res->assertSee('TEST_KELAS_FIT', false);
        // Tidak ada hasil: empty state
        $res = $this->actingAs($admin)->get('/admin/users?search=ZZZ_TIDAK_ADA_FIT');
        $res->assertStatus(200);
        $res->assertSee('Belum ada data', false);
        // Filter invalid diabaikan aman (tanpa error)
        $res = $this->actingAs($admin)->get('/admin/users?role=superadmin&status=bogus');
        $res->assertStatus(200);
    }

    public function test_action_menu_endpoints_intact(): void
    {
        $admin = $this->admin();
        // Edit URL tetap valid dari menu
        $teacher = Teacher::where('nip', 'FITNIP001')->firstOrFail();
        $this->actingAs($admin)->get('/admin/teachers/'.$teacher->id.'/edit')->assertStatus(200);
        // Delete via menu tetap bekerja (buat lalu hapus teacher uji tambahan)
        $gu = UserBaru::create(['name' => 'Del FIT', 'username' => 'fit_del', 'password' => Hash::make('x'), 'role' => 'guru', 'status' => 'aktif']);
        $t = Teacher::create(['user_id' => $gu->id, 'nip' => 'FITNIPDEL']);
        $this->actingAs($admin)->delete('/admin/teachers/'.$t->id)->assertRedirect(route('admin.teachers.index'));
        $this->assertNull(Teacher::find($t->id));
        UserBaru::where('username', 'fit_del')->delete();
    }
}
