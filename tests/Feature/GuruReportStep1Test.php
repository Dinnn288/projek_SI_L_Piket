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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuruReportStep1Test extends TestCase
{
    private function reconnect(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket']);
        DB::reconnect('mysql');
    }

    private function makePhoto(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $tmp = tempnam(sys_get_temp_dir(), 'f7s1').'.png';
        file_put_contents($tmp, $png);
        clearstatcache(true, $tmp);

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Storage::fake('local');

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F7S1'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F7S1')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F7S1', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F7S1'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        // Siswa pembuat laporan
        $siswaUser = UserBaru::where('username', 'f7s1_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa F7S1', 'username' => 'f7s1_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        if (! Student::where('user_id', $siswaUser->id)->exists()) {
            Student::create(['user_id' => $siswaUser->id, 'nis' => 'F7S1NIS001', 'class_id' => $class->id]);
        }

        // Guru A dan B: piket hari ini. Guru C: tidak piket.
        $today = GuruDutyService::todayDay() ?? 'senin';
        foreach ([['f7s1_guru_a', 'F7S1NIP_A'], ['f7s1_guru_b', 'F7S1NIP_B']] as [$u, $nip]) {
            $gu = UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => "Guru $u", 'username' => $u, 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
            $teacher = Teacher::where('user_id', $gu->id)->first();
            if (! $teacher) {
                $teacher = Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            }
            if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
                DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
            }
        }
        $guC = UserBaru::where('username', 'f7s1_guru_c')->first()
            ?: UserBaru::create(['name' => 'Guru C', 'username' => 'f7s1_guru_c', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        if (! Teacher::where('user_id', $guC->id)->exists()) {
            Teacher::create(['user_id' => $guC->id, 'nip' => 'F7S1NIP_C']);
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            $sids = Student::whereIn('nis', ['F7S1NIS001'])->pluck('id');
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

    public static function cleanupTestMasters(): void
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $sids = App\Models\Student::where('nis', 'F7S1NIS001')->pluck('id');
        $rids = App\Models\Report::whereIn('student_id', $sids)->pluck('id');
        if ($rids->isNotEmpty()) {
            App\Models\ReportHistory::whereIn('report_id', $rids)->delete();
            App\Models\ReportAttachment::whereIn('report_id', $rids)->delete();
            App\Models\Report::whereIn('id', $rids)->delete();
        }
        App\Models\Student::where('nis', 'F7S1NIS001')->delete();
        $guruIds = App\Models\UserBaru::whereIn('username', ['f7s1_guru_a', 'f7s1_guru_b', 'f7s1_guru_c'])->pluck('id');
        $teacherIds = App\Models\Teacher::whereIn('user_id', $guruIds)->pluck('id');
        if ($teacherIds->isNotEmpty()) App\Models\DutySchedule::whereIn('teacher_id', $teacherIds)->delete();
        App\Models\Teacher::whereIn('user_id', $guruIds)->delete();
        App\Models\UserBaru::whereIn('username', ['f7s1_siswa', 'f7s1_guru_a', 'f7s1_guru_b', 'f7s1_guru_c'])->delete();
        App\Models\SchoolClass::where('name', 'TEST_KELAS_F7S1')->delete();
        App\Models\Major::where('name', 'TEST_MAJOR_F7S1')->delete();
        App\Models\ReportCategory::where('name', 'TEST_Kat_F7S1')->delete();
    }

    private function createReport(string $title = 'Lampu mati'): Report
    {
        $st = Student::where('nis', 'F7S1NIS001')->firstOrFail();
        $cat = ReportCategory::where('name', 'TEST_Kat_F7S1')->firstOrFail();
        $report = Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'Deskripsi '.$title,
            'location' => 'Kelas - lokasi', 'priority' => $cat->priority, 'status' => 'menunggu',
        ]);
        $photo = $this->makePhoto('a.png');
        $path = $photo->store('reports', 'local');
        $report->attachments()->create([
            'file_patch' => $path, 'original_name' => 'a.png',
            'file_size' => $photo->getSize(), 'mime_type' => 'image/png',
        ]);

        return $report;
    }

    private function guru(string $username): UserBaru
    {
        return UserBaru::where('username', $username)->firstOrFail();
    }

    private function teacherOf(string $username): Teacher
    {
        return Teacher::where('user_id', $this->guru($username)->id)->firstOrFail();
    }

    private function withinHours(): bool
    {
        Carbon::setTestNow(null);

        return GuruDutyService::isWithinHours();
    }

    private function freezeMorning(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));
    }

    public function test_dashboard_ok(): void
    {
        $this->actingAs($this->guru('f7s1_guru_a'))->get('/guru')->assertStatus(200)->assertSee('Laporan menunggu');
    }

    public function test_index_ok(): void
    {
        $this->actingAs($this->guru('f7s1_guru_a'))->get('/guru/laporan')->assertStatus(200);
    }

    public function test_show_waiting_by_onduty_guru(): void
    {
        $rep = $this->createReport('R-wait');
        $res = $this->actingAs($this->guru('f7s1_guru_a'))->get('/guru/laporan/'.$rep->id);
        $res->assertStatus(200);
        if ($this->withinHours()) {
            $res->assertSee('Ambil Laporan');
        } else {
            $res->assertSee('07.00-16.00');
        }
    }

    public function test_show_waiting_by_offduty_guru_rejected(): void
    {
        $rep = $this->createReport('R-offduty');
        // A2: tak-berhak -> 404 (samarkan eksistensi), bukan 403.
        $this->actingAs($this->guru('f7s1_guru_c'))->get('/guru/laporan/'.$rep->id)->assertStatus(404);
    }

    public function test_claim_success_by_onduty_guru(): void
    {
        $this->freezeMorning();
        $rep = $this->createReport('R-claim-ok');
        $teacherA = $this->teacherOf('f7s1_guru_a');
        $this->actingAs($this->guru('f7s1_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/claim')
            ->assertRedirect()
            ->assertSessionHas('success', 'Laporan berhasil diambil.');
        $fresh = $rep->fresh();
        $this->assertEquals($teacherA->id, (int) $fresh->assigned_teacher_id);
        $this->assertEquals('dalam_penanganan', $fresh->status);
        $this->assertNotNull($fresh->assigned_at);
        $hist = ReportHistory::where('report_id', $rep->id)->where('action', 'claimed')->first();
        $this->assertNotNull($hist);
        $this->assertEquals('menunggu', $hist->from_status);
        $this->assertEquals('dalam_penanganan', $hist->to_status);
    }

    public function test_claim_rejected_offduty_guru(): void
    {
        $rep = $this->createReport('R-claim-off');
        $this->actingAs($this->guru('f7s1_guru_c'))
            ->post('/guru/laporan/'.$rep->id.'/claim')
            ->assertRedirect();
        $fresh = $rep->fresh();
        $this->assertNull($fresh->assigned_teacher_id);
        $this->assertEquals('menunggu', $fresh->status);
    }

    public function test_double_claim_only_first_wins(): void
    {
        $this->freezeMorning();
        $rep = $this->createReport('R-race');
        $teacherA = $this->teacherOf('f7s1_guru_a');
        // Guru A claim duluan -> sukses
        $this->actingAs($this->guru('f7s1_guru_a'))->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('success');
        $this->assertEquals($teacherA->id, (int) $rep->fresh()->assigned_teacher_id);
        // Guru B claim laporan yang sama -> ditolak
        $res = $this->actingAs($this->guru('f7s1_guru_b'))->post('/guru/laporan/'.$rep->id.'/claim');
        $res->assertSessionHas('error', 'Laporan sudah diambil oleh Guru Piket lain.');
        $this->assertEquals($teacherA->id, (int) $rep->fresh()->assigned_teacher_id);
        // Guru A claim ulang -> ditolak, tidak ada perubahan
        $this->actingAs($this->guru('f7s1_guru_a'))->post('/guru/laporan/'.$rep->id.'/claim')
            ->assertSessionHas('error', 'Anda sudah menangani laporan ini.');
        $this->assertEquals($teacherA->id, (int) $rep->fresh()->assigned_teacher_id);
        $this->assertEquals(1, ReportHistory::where('report_id', $rep->id)->where('action', 'claimed')->count());
    }

    public function test_manipulasi_request_diabaikan(): void
    {
        $this->freezeMorning();
        $rep = $this->createReport('R-manip');
        $teacherB = $this->teacherOf('f7s1_guru_b');
        $this->actingAs($this->guru('f7s1_guru_a'))->post('/guru/laporan/'.$rep->id.'/claim', [
            'teacher_id' => $teacherB->id,
            'assigned_teacher_id' => $teacherB->id,
            'status' => 'selesai',
        ])->assertSessionHas('success');
        $fresh = $rep->fresh();
        $this->assertNotEquals($teacherB->id, (int) $fresh->assigned_teacher_id);
        $this->assertEquals('dalam_penanganan', $fresh->status);
    }

    public function test_roles_guest(): void
    {
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $siswa = UserBaru::where('username', 'f7s1_siswa')->first();
        $kepsek = UserBaru::where('role', 'kepala_sekolah')->where('status', 'aktif')->first();
        if ($admin) $this->actingAs($admin)->get('/guru')->assertStatus(403);
        if ($siswa) $this->actingAs($siswa)->get('/guru')->assertStatus(403);
        if ($kepsek) $this->actingAs($kepsek)->get('/guru')->assertStatus(403);
        $this->refreshApplication();
        Storage::fake('local');
        $this->get('/guru')->assertRedirect('/login');
        $this->get('/guru/laporan')->assertRedirect('/login');
    }

    public function test_claim_rejected_outside_hours(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(18, 0));
        $rep = $this->createReport('R-night');
        $this->actingAs($this->guru('f7s1_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/claim')
            ->assertSessionHas('error', 'Pengambilan laporan hanya dapat dilakukan pada jam piket 07.00-16.00.');
        $this->assertNull($rep->fresh()->assigned_teacher_id);
        Carbon::setTestNow(null);
    }

    public function test_404_unknown_report(): void
    {
        $this->actingAs($this->guru('f7s1_guru_a'))->get('/guru/laporan/999999')->assertStatus(404);
    }
}




