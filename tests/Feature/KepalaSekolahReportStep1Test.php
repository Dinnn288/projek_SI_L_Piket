<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportCategory;
use App\Models\ReportHistory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KepalaSekolahReportStep1Test extends TestCase
{
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
        $tmp = tempnam(sys_get_temp_dir(), 'f8s1').'.png';
        file_put_contents($tmp, $png);
        clearstatcache(true, $tmp);

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Storage::fake('local');

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F8S1'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F8S1')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F8S1', 'status' => 'aktif']);
        ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F8S1'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $siswaUser = UserBaru::where('username', 'f8s1_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa F8S1', 'username' => 'f8s1_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        if (! Student::where('user_id', $siswaUser->id)->exists()) {
            Student::create(['user_id' => $siswaUser->id, 'nis' => 'F8S1NIS001', 'class_id' => $class->id]);
        }
        $guruUser = UserBaru::where('username', 'f8s1_guru')->first()
            ?: UserBaru::create(['name' => 'Guru F8S1', 'username' => 'f8s1_guru', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        if (! Teacher::where('user_id', $guruUser->id)->exists()) {
            Teacher::create(['user_id' => $guruUser->id, 'nip' => 'F8S1NIP']);
        }
        if (! UserBaru::where('username', 'f8s1_kepsek')->exists()) {
            UserBaru::create(['name' => 'Kepsek F8S1', 'username' => 'f8s1_kepsek', 'password' => Hash::make('password123'), 'role' => 'kepala_sekolah', 'status' => 'aktif']);
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            $sids = Student::where('nis', 'F8S1NIS001')->pluck('id');
            $rids = Report::whereIn('student_id', $sids)->pluck('id');
            if ($rids->isNotEmpty()) {
                ReportHistory::whereIn('report_id', $rids)->delete();
                ReportAttachment::whereIn('report_id', $rids)->delete();
                Report::whereIn('id', $rids)->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function kepsek(): UserBaru
    {
        return UserBaru::where('username', 'f8s1_kepsek')->firstOrFail();
    }

    private function createForwardedReport(string $title, string $note): Report
    {
        $st = Student::where('nis', 'F8S1NIS001')->firstOrFail();
        $cat = ReportCategory::where('name', 'TEST_Kat_F8S1')->firstOrFail();
        $teacher = Teacher::where('nip', 'F8S1NIP')->firstOrFail();
        $guruUser = UserBaru::where('username', 'f8s1_guru')->firstOrFail();
        $report = Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'Deskripsi asli '.$title,
            'location' => 'Kelas - lokasi', 'priority' => $cat->priority,
            'status' => 'diteruskan', 'assigned_teacher_id' => $teacher->id, 'assigned_at' => now(),
        ]);
        $photo = $this->makePhoto('a.png');
        $path = $photo->store('reports', 'local');
        $report->attachments()->create([
            'file_patch' => $path, 'original_name' => 'a.png',
            'file_size' => $photo->getSize(), 'mime_type' => 'image/png',
        ]);
        ReportHistory::create([
            'report_id' => $report->id, 'user_id' => $guruUser->id,
            'action' => 'claimed', 'from_status' => 'menunggu',
            'to_status' => 'dalam_penanganan', 'note' => null,
        ]);
        ReportHistory::create([
            'report_id' => $report->id, 'user_id' => $guruUser->id,
            'action' => 'forwarded', 'from_status' => 'dalam_penanganan',
            'to_status' => 'diteruskan', 'note' => $note,
        ]);

        return $report;
    }

    private function createOtherStatusReport(string $title, string $status): Report
    {
        $st = Student::where('nis', 'F8S1NIS001')->firstOrFail();
        $cat = ReportCategory::where('name', 'TEST_Kat_F8S1')->firstOrFail();

        return Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'd', 'location' => 'l',
            'priority' => $cat->priority, 'status' => $status,
        ]);
    }

    public function test_auth_login_logout(): void
    {
        $this->post('/login', ['username' => 'f8s1_kepsek', 'password' => 'password123'])->assertRedirect('/kepala-sekolah');
        $this->refreshApplication();
        Storage::fake('local');
        $this->get('/kepala-sekolah')->assertRedirect('/login');
    }

    public function test_authorization_roles(): void
    {
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $siswa = UserBaru::where('username', 'f8s1_siswa')->first();
        $guru = UserBaru::where('username', 'f8s1_guru')->first();
        if ($admin) $this->actingAs($admin)->get('/kepala-sekolah')->assertStatus(403);
        if ($siswa) $this->actingAs($siswa)->get('/kepala-sekolah')->assertStatus(403);
        if ($guru) $this->actingAs($guru)->get('/kepala-sekolah')->assertStatus(403);
        $this->actingAs($this->kepsek())->get('/kepala-sekolah')->assertStatus(200);
    }

    public function test_dashboard_counts(): void
    {
        $this->createForwardedReport('F-1', 'Alasan satu.');
        $this->createOtherStatusReport('Selesai-1', 'selesai');
        $res = $this->actingAs($this->kepsek())->get('/kepala-sekolah');
        $res->assertStatus(200);
        $res->assertSee('<div class="value">1</div>', false);
        $res->assertSee('F-1');
    }

    public function test_list_only_forwarded(): void
    {
        $fwd = $this->createForwardedReport('F-list', 'Alasan list.');
        $this->createOtherStatusReport('W-list', 'menunggu');
        $this->createOtherStatusReport('P-list', 'dalam_penanganan');
        $this->createOtherStatusReport('S-list', 'selesai');
        $res = $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan');
        $res->assertStatus(200);
        $res->assertSee('F-list');
        $res->assertSee('Alasan list.');
        $res->assertDontSee('W-list');
        $res->assertDontSee('P-list');
        $res->assertDontSee('S-list');
        $this->assertNotNull($fwd->fresh());
    }

    public function test_detail_forwarded_complete(): void
    {
        $rep = $this->createForwardedReport('F-detail', 'Butuh tindak lanjut sekolah.');
        $res = $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep->id);
        $res->assertStatus(200);
        $res->assertSee('Deskripsi asli F-detail');
        $res->assertSee('Butuh tindak lanjut sekolah.');
        $res->assertSee('Guru F8S1');
        $res->assertSee('Siswa F8S1');
        $res->assertSee('TEST_KELAS_F8S1');
        // Read-only: counts unchanged
        $this->assertEquals(2, ReportHistory::where('report_id', $rep->id)->count());
        $this->assertEquals('diteruskan', $rep->fresh()->status);
        $this->assertEquals('Deskripsi asli F-detail', $rep->fresh()->description);
    }

    public function test_non_forwarded_and_unknown_404(): void
    {
        // A1: kepsek hanya boleh buka diteruskan / dalam_penanganan
        // (monitoring) / pernah forwarded. Menunggu tanpa riwayat -> 404.
        $other = $this->createOtherStatusReport('W-404', 'menunggu');
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$other->id)->assertStatus(404);
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/999999')->assertStatus(404);
    }

    public function test_no_mutation_routes(): void
    {
        $rep = $this->createForwardedReport('F-ro', 'Alasan.');
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id, [])->assertStatus(405);
        $this->actingAs($this->kepsek())->put('/kepala-sekolah/laporan/'.$rep->id, [])->assertStatus(405);
        $this->actingAs($this->kepsek())->delete('/kepala-sekolah/laporan/'.$rep->id)->assertStatus(405);
        $this->assertEquals('diteruskan', $rep->fresh()->status);
    }
}




