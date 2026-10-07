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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KepsekMonitoringTest extends TestCase
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
        config(['session.driver' => 'database']);
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_KM'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_KM')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_KM', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_KM'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswa = $mkUser('km_siswa', 'Siswa KM', 'siswa');
        $guru = $mkUser('km_guru', 'Guru KM', 'guru');
        $kepsek = $mkUser('km_kepsek', 'Kepsek KM', 'kepala_sekolah');
        $admin = $mkUser('km_admin', 'Admin KM', 'admin');
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'KMNIS001', 'class_id' => $class->id]);
        }
        $teacher = Teacher::where('user_id', $guru->id)->first()
            ?: Teacher::create(['user_id' => $guru->id, 'nip' => 'KMNIP001']);
        $today = GuruDutyService::todayDay() ?? 'senin';
        if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
            DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
        }

        $this->ids = [
            'major' => $major->id, 'class' => $class->id, 'cat' => $cat->id,
            'siswa' => $siswa->id, 'guru' => $guru->id, 'kepsek' => $kepsek->id,
            'admin' => $admin->id, 'teacher' => $teacher->id,
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $sids = Student::where('nis', 'KMNIS001')->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::where('nis', 'KMNIS001')->delete();
                $gids = UserBaru::whereIn('username', ['km_siswa', 'km_guru'])->pluck('id');
                $tids = Teacher::whereIn('user_id', $gids)->pluck('id');
                if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->delete();
                Teacher::whereIn('user_id', $gids)->delete();
                UserBaru::whereIn('username', ['km_siswa', 'km_guru', 'km_kepsek', 'km_admin'])->delete();
                ReportCategory::whereKey($this->ids['cat'] ?? 0)->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function user(string $key): UserBaru
    {
        return UserBaru::findOrFail($this->ids[$key]);
    }

    private function createReport(string $title, string $status = 'menunggu', ?int $teacherId = null): Report
    {
        $st = Student::where('nis', 'KMNIS001')->firstOrFail();

        return Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $this->ids['cat'],
            'title' => $title, 'description' => 'Deskripsi '.$title, 'location' => 'Kelas KM',
            'priority' => 'tinggi', 'status' => $status,
            'assigned_teacher_id' => $teacherId, 'assigned_at' => $teacherId ? now() : null,
        ]);
    }

    public function test_authorization(): void
    {
        $this->actingAs($this->user('kepsek'))->get('/kepala-sekolah/monitoring-dalam-penanganan')->assertStatus(200);
        $this->actingAs($this->user('siswa'))->get('/kepala-sekolah/monitoring-dalam-penanganan')->assertStatus(403);
        $this->actingAs($this->user('guru'))->get('/kepala-sekolah/monitoring-dalam-penanganan')->assertStatus(403);
        $this->actingAs($this->user('admin'))->get('/kepala-sekolah/monitoring-dalam-penanganan')->assertStatus(403);
        $this->refreshApplication();
        $this->reconnect();
        $this->get('/kepala-sekolah/monitoring-dalam-penanganan')->assertRedirect('/login');
        // Dashboard memuat tabel monitoring + polling
        $kepsek = UserBaru::where('username', 'km_kepsek')->firstOrFail();
        $res = $this->actingAs($kepsek)->get('/kepala-sekolah');
        $res->assertStatus(200);
        $res->assertSee('Laporan Sedang Ditangani Guru', false);
        $res->assertSee('slMonitoringBody', false);
    }

    public function test_data_visibility_lifecycle(): void
    {
        $kepsek = $this->user('kepsek');
        $guru = $this->user('guru');
        $teacherId = $this->ids['teacher'];

        // Belum diambil: tidak masuk monitoring
        $waiting = $this->createReport('KM-Waiting');
        $res = $this->actingAs($kepsek)->getJson('/kepala-sekolah/monitoring-dalam-penanganan');
        $res->assertStatus(200);
        $this->assertFalse(collect($res->json('reports'))->contains('id', $waiting->id));

        // Setelah claim: muncul dengan guru + waktu yang benar
        $this->actingAs($guru)->post('/guru/laporan/'.$waiting->id.'/claim')->assertSessionHas('success');
        $res = $this->actingAs($kepsek)->getJson('/kepala-sekolah/monitoring-dalam-penanganan');
        $res->assertStatus(200);
        $item = collect($res->json('reports'))->firstWhere('id', $waiting->id);
        $this->assertNotNull($item);
        $this->assertEquals('Guru KM', $item['teacher']);
        $this->assertEquals('Siswa KM', $item['student']);
        $this->assertEquals('TEST_KELAS_KM', $item['class']);
        $this->assertNotEmpty($item['assigned_at']);
        $this->assertEquals('dalam_penanganan', $item['status']);
        $this->assertStringContainsString('/kepala-sekolah/laporan/'.$waiting->id, $item['detail_url']);
        $this->assertNotEmpty($res->json('server_time'));

        // Selesai: keluar dari monitoring
        $this->actingAs($guru)->post('/guru/laporan/'.$waiting->id.'/complete', ['note' => 'Selesai.'])->assertSessionHas('success');
        $res = $this->actingAs($kepsek)->getJson('/kepala-sekolah/monitoring-dalam-penanganan');
        $this->assertFalse(collect($res->json('reports'))->contains('id', $waiting->id));

        // Forward: workflow lama tetap (diteruskan, notif kepsek)
        $rep2 = $this->createReport('KM-Forward');
        $this->actingAs($guru)->post('/guru/laporan/'.$rep2->id.'/claim')->assertSessionHas('success');
        $this->actingAs($guru)->post('/guru/laporan/'.$rep2->id.'/forward', ['note' => 'Butuh kepsek.'])->assertSessionHas('success');
        $this->assertEquals('diteruskan', $rep2->fresh()->status);
        $this->assertNotNull(Notification::where('report_id', $rep2->id)->where('title', 'Laporan Diteruskan')->first());
        $res = $this->actingAs($kepsek)->getJson('/kepala-sekolah/monitoring-dalam-penanganan');
        $this->assertFalse(collect($res->json('reports'))->contains('id', $rep2->id));

        // Selesai langsung tidak pernah masuk monitoring aktif
        $rep3 = $this->createReport('KM-Done', 'selesai', $teacherId);
        $res = $this->actingAs($kepsek)->getJson('/kepala-sekolah/monitoring-dalam-penanganan');
        $this->assertFalse(collect($res->json('reports'))->contains('id', $rep3->id));
    }

    public function test_json_shape_and_isolation(): void
    {
        $kepsek = $this->user('kepsek');
        $rep = $this->createReport('KM-Shape', 'dalam_penanganan', $this->ids['teacher']);
        $rep->update(['assigned_at' => now()]);
        $res = $this->actingAs($kepsek)->getJson('/kepala-sekolah/monitoring-dalam-penanganan');
        $res->assertStatus(200);
        $res->assertJsonStructure(['server_time', 'count', 'reports' => [['id', 'title', 'student', 'class', 'location', 'teacher', 'assigned_at', 'status', 'detail_url']]]);
        // Tidak ada password/hash/data sensitif di payload
        $this->assertStringNotContainsString('password', strtolower($res->getContent()));
    }
}
