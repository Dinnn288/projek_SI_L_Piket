<?php

namespace Tests\Feature;

use App\Models\DutySchedule;
use App\Models\Major;
use App\Models\ReportCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSearchTest extends TestCase
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

        $admin = UserBaru::where('username', 'fsch_admin')->first()
            ?: UserBaru::create(['name' => 'Admin FSCH', 'username' => 'fsch_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);
        $siswa = UserBaru::where('username', 'fsch_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa FSCH', 'username' => 'fsch_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);

        $this->ids = ['admin' => $admin->id, 'siswa' => $siswa->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $uids = UserBaru::whereIn('username', ['fsch_zuser', 'fsch_zguru', 'fsch_zsiswa'])->pluck('id');
                $tids = Teacher::whereIn('user_id', $uids)->pluck('id');
                if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->delete();
                Teacher::whereIn('user_id', $uids)->delete();
                Student::whereIn('user_id', $uids)->delete();
                UserBaru::whereIn('username', ['fsch_zuser', 'fsch_zguru', 'fsch_zsiswa'])->delete();
                Major::where('name', 'FSCH_ZJurusan')->delete();
                SchoolClass::where('name', 'FSCH_ZKelas')->delete();
                ReportCategory::where('name', 'FSCH_ZKategori')->delete();
                UserBaru::whereIn('id', [$this->ids['admin'] ?? 0, $this->ids['siswa'] ?? 0])->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function admin(): UserBaru
    {
        return UserBaru::findOrFail($this->ids['admin']);
    }

    public function test_search_users_majors_classes(): void
    {
        $admin = $this->admin();
        UserBaru::create(['name' => 'Zed FSCH', 'username' => 'fsch_zuser', 'password' => Hash::make('x'), 'role' => 'guru', 'status' => 'aktif']);
        Major::create(['name' => 'FSCH_ZJurusan', 'status' => 'aktif']);
        $major = Major::where('name', 'FSCH_ZJurusan')->firstOrFail();
        SchoolClass::create(['major_id' => $major->id, 'name' => 'FSCH_ZKelas', 'status' => 'aktif']);

        $res = $this->actingAs($admin)->get('/admin/users?search=fsch_zuser');
        $res->assertStatus(200);
        $res->assertSee('fsch_zuser', false);

        $res = $this->actingAs($admin)->get('/admin/majors?search=FSCH_ZJurusan');
        $res->assertStatus(200);
        $res->assertSee('FSCH_ZJurusan', false);

        $res = $this->actingAs($admin)->get('/admin/classes?search=FSCH_ZKelas');
        $res->assertStatus(200);
        $res->assertSee('FSCH_ZKelas', false);

        // Tidak ada hasil: empty state + tanpa error
        $res = $this->actingAs($admin)->get('/admin/users?search=ZZZ_TIDAK_ADA_FSCH');
        $res->assertStatus(200);
        $res->assertSee('Belum ada data', false);
    }

    public function test_search_students_teachers_categories_schedules(): void
    {
        $admin = $this->admin();
        $major = Major::create(['name' => 'FSCH_ZJurusan', 'status' => 'aktif']);
        $class = SchoolClass::create(['major_id' => $major->id, 'name' => 'FSCH_ZKelas', 'status' => 'aktif']);
        $gu = UserBaru::create(['name' => 'Zed Guru', 'username' => 'fsch_zguru', 'password' => Hash::make('x'), 'role' => 'guru', 'status' => 'aktif']);
        Teacher::create(['user_id' => $gu->id, 'nip' => 'FSCHZNIP001']);
        $su = UserBaru::create(['name' => 'Zed Siswa', 'username' => 'fsch_zsiswa', 'password' => Hash::make('x'), 'role' => 'siswa', 'status' => 'aktif']);
        Student::create(['user_id' => $su->id, 'nis' => 'FSCHZNIS001', 'class_id' => $class->id]);
        ReportCategory::create(['name' => 'FSCH_ZKategori', 'status' => 'aktif', 'priority' => 'tinggi']);
        DutySchedule::create(['teacher_id' => Teacher::where('user_id', $gu->id)->value('id'), 'day' => 'senin']);

        $this->actingAs($admin)->get('/admin/students?search=FSCHZNIS001')->assertStatus(200)->assertSee('FSCHZNIS001', false);
        $this->actingAs($admin)->get('/admin/teachers?search=FSCHZNIP001')->assertStatus(200)->assertSee('FSCHZNIP001', false);
        $this->actingAs($admin)->get('/admin/report-categories?search=FSCH_ZKategori')->assertStatus(200)->assertSee('FSCH_ZKategori', false);
        // Search jadwal by hari: Senin tampil, Selasa kosong untuk data uji
        $senin = $this->actingAs($admin)->get('/admin/duty-schedules?search=senin');
        $senin->assertStatus(200);
        $senin->assertSee('FSCHZNIP001', false);
        // Reset: tanpa search tampil kembali
        $this->actingAs($admin)->get('/admin/duty-schedules')->assertStatus(200)->assertSee('FSCHZNIP001', false);
        // Pagination dipertahankan: param search tetap di link halaman
        $paged = $this->actingAs($admin)->get('/admin/users?search=fsch_zuser&page=1');
        $paged->assertStatus(200);
    }

    public function test_search_authorization(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswa']);
        $this->actingAs($siswa)->get('/admin/users?search=x')->assertStatus(403);
        $this->refreshApplication();
        $this->reconnect();
        $this->get('/admin/users?search=x')->assertRedirect('/login');
    }
}
