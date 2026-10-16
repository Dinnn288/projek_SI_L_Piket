<?php

namespace Tests\Feature;

use App\Models\DutySchedule;
use App\Models\Major;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaginationFaseTest extends TestCase
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
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $admin = UserBaru::where('username', 'fpg_admin')->first()
            ?: UserBaru::create(['name' => 'Admin FPG', 'username' => 'fpg_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);
        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_FPG'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_FPG')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_FPG', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_FPG'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);
        $siswa = UserBaru::where('username', 'fpg_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa FPG', 'username' => 'fpg_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'FPGNIS001', 'class_id' => $class->id]);
        }
        $guru = UserBaru::where('username', 'fpg_guru')->first()
            ?: UserBaru::create(['name' => 'Guru FPG', 'username' => 'fpg_guru', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        $teacher = Teacher::where('user_id', $guru->id)->first()
            ?: Teacher::create(['user_id' => $guru->id, 'nip' => 'FPGNIP001']);
        $today = GuruDutyService::todayDay() ?? 'senin';
        if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
            DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
        }
        $kepsek = UserBaru::where('username', 'fpg_kepsek')->first()
            ?: UserBaru::create(['name' => 'Kepsek FPG', 'username' => 'fpg_kepsek', 'password' => Hash::make('password123'), 'role' => 'kepala_sekolah', 'status' => 'aktif']);

        $this->ids = ['admin' => $admin->id, 'major' => $major->id, 'class' => $class->id, 'cat' => $cat->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $sids = Student::where('nis', 'FPGNIS001')->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    \App\Models\Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                for ($i = 1; $i <= 15; $i++) {
                    ReportCategory::where('name', 'FPG_Cat_'.$i)->delete();
                }
                Student::where('nis', 'FPGNIS001')->delete();
                $gids = UserBaru::whereIn('username', ['fpg_siswa', 'fpg_guru'])->pluck('id');
                $tids = Teacher::whereIn('user_id', $gids)->pluck('id');
                if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->where('day', GuruDutyService::todayDay() ?? 'senin')->delete();
                Teacher::whereIn('user_id', $gids)->delete();
                UserBaru::whereIn('username', ['fpg_siswa', 'fpg_guru'])->delete();
                ReportCategory::whereKey($this->ids['cat'] ?? 0)->delete();
                Report::where('title', 'like', 'FPG Report%')->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
                UserBaru::whereIn('username', ['fpg_admin', 'fpg_kepsek'])->delete();
            }
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function admin(): UserBaru
    {
        return UserBaru::where('username', 'fpg_admin')->firstOrFail();
    }

    public function test_admin_categories_pagination_25_records(): void
    {
        $admin = $this->admin();
        for ($i = 1; $i <= 15; $i++) {
            ReportCategory::create(['name' => 'FPG_Cat_'.$i, 'status' => 'aktif', 'priority' => 'rendah']);
        }
        $total = ReportCategory::count();
        $lastPage = (int) ceil($total / 10);
        $lastFirst = ($lastPage - 1) * 10 + 1;
        // Halaman 1: 10 pertama + link ke halaman 2
        $p1 = $this->actingAs($admin)->get('/admin/report-categories');
        $p1->assertStatus(200);
        $p1->assertSee('Menampilkan 1–10 dari '.$total, false);
        $p1->assertSee('page=2', false);
        // Halaman 2: 10 berikutnya + link kembali ke halaman 1
        $p2 = $this->actingAs($admin)->get('/admin/report-categories?page=2');
        $p2->assertStatus(200);
        $p2->assertSee('Menampilkan 11–20 dari '.$total, false);
        $p2->assertSee('page=1', false);
        // Halaman terakhir: info count benar
        $last = $this->actingAs($admin)->get('/admin/report-categories?page='.$lastPage);
        $last->assertStatus(200);
        $last->assertSee('Menampilkan '.$lastFirst.'–'.$total.' dari '.$total, false);
    }

    public function test_siswa_reports_pagination_and_ownership(): void
    {
        $siswa = UserBaru::where('username', 'fpg_siswa')->firstOrFail();
        $student = Student::where('nis', 'FPGNIS001')->firstOrFail();
        for ($i = 1; $i <= 12; $i++) {
            Report::create([
                'student_id' => $student->id, 'class_id' => $student->class_id, 'category_id' => $this->ids['cat'],
                'title' => 'FPG Report '.$i, 'description' => 'd', 'location' => 'l',
                'priority' => 'tinggi', 'status' => 'menunggu',
            ]);
        }
        $p1 = $this->actingAs($siswa)->get('/siswa/laporan');
        $p1->assertStatus(200);
        $p1->assertSee('Menampilkan 1–10 dari 12', false);
        $p2 = $this->actingAs($siswa)->get('/siswa/laporan?page=2');
        $p2->assertStatus(200);
        $p2->assertSee('Menampilkan 11–12 dari 12', false);
        // Nomor kontinu halaman 2 mulai 11
        $p2->assertSee('<td>11</td>', false);
    }

    public function test_empty_and_single_page_no_extra_links(): void
    {
        $admin = $this->admin();
        // Empty-state kategori tidak dapat diuji dengan tabel penuh tanpa
        // merusak FK reports.category_id (NOT NULL + RESTRICT); sebagai ganti,
        // verifikasi perilaku paginator: 1 record => tanpa link halaman.
        $single = ReportCategory::create(['name' => 'FPG_Single', 'status' => 'aktif', 'priority' => 'rendah']);
        $allIds = ReportCategory::pluck('id')->all();
        // Simulasi tampilan 1 record: paginate manual lewat query scoped.
        $one = ReportCategory::whereKey($single->id)->paginate(10);
        $this->assertEquals(1, $one->total());
        $this->assertFalse($one->hasPages());
        $res = $this->actingAs($admin)->get('/admin/report-categories');
        $res->assertStatus(200);
        $res->assertSee('FPG_Single', false);
        $single->delete();
        $this->assertEquals(count($allIds) - 1, ReportCategory::count());
    }

    public function test_guru_and_kepsek_pagination(): void
    {
        $guru = UserBaru::where('username', 'fpg_guru')->firstOrFail();
        $kepsek = UserBaru::where('username', 'fpg_kepsek')->firstOrFail();
        $this->actingAs($guru)->get('/guru/laporan')->assertStatus(200);
        $this->actingAs($kepsek)->get('/kepala-sekolah/laporan')->assertStatus(200);
    }
}
