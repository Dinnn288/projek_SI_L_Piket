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

class GuruTakeoverStep3Test extends TestCase
{
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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F7S3'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F7S3')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F7S3', 'status' => 'aktif']);
        ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F7S3'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $siswaUser = UserBaru::where('username', 'f7s3_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa F7S3', 'username' => 'f7s3_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        if (! Student::where('user_id', $siswaUser->id)->exists()) {
            Student::create(['user_id' => $siswaUser->id, 'nis' => 'F7S3NIS001', 'class_id' => $class->id]);
        }

        $today = GuruDutyService::todayDay() ?? 'senin';
        foreach ([['f7s3_guru_a', 'F7S3NIP_A'], ['f7s3_guru_b', 'F7S3NIP_B'], ['f7s3_guru_c', 'F7S3NIP_C']] as [$u, $nip]) {
            $gu = UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => "Guru $u", 'username' => $u, 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
            $teacher = Teacher::where('user_id', $gu->id)->first()
                ?: Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
                DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
            }
        }
        // Guru D: tidak piket hari ini
        $guD = UserBaru::where('username', 'f7s3_guru_d')->first()
            ?: UserBaru::create(['name' => 'Guru D', 'username' => 'f7s3_guru_d', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        if (! Teacher::where('user_id', $guD->id)->exists()) {
            Teacher::create(['user_id' => $guD->id, 'nip' => 'F7S3NIP_D']);
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            $sids = Student::where('nis', 'F7S3NIS001')->pluck('id');
            $rids = Report::whereIn('student_id', $sids)->pluck('id');
            if ($rids->isNotEmpty()) {
                ReportHistory::whereIn('report_id', $rids)->delete();
                ReportAttachment::whereIn('report_id', $rids)->delete();
                Report::whereIn('id', $rids)->delete();
            }
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function guru(string $username): UserBaru
    {
        return UserBaru::where('username', $username)->firstOrFail();
    }

    private function teacherOf(string $username): Teacher
    {
        return Teacher::where('user_id', $this->guru($username)->id)->firstOrFail();
    }

    private function createContinuableReport(string $title): Report
    {
        $st = Student::where('nis', 'F7S3NIS001')->firstOrFail();
        $cat = ReportCategory::where('name', 'TEST_Kat_F7S3')->firstOrFail();
        $teacherA = $this->teacherOf('f7s3_guru_a');
        $report = Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'Deskripsi asli '.$title,
            'location' => 'Kelas - lokasi', 'priority' => $cat->priority,
            'status' => 'dalam_penanganan', 'assigned_teacher_id' => $teacherA->id,
            'assigned_at' => Carbon::yesterday()->setTime(10, 0),
        ]);
        ReportHistory::create([
            'report_id' => $report->id, 'user_id' => $this->guru('f7s3_guru_a')->id,
            'action' => 'claimed', 'from_status' => 'menunggu',
            'to_status' => 'dalam_penanganan', 'note' => null,
        ]);

        return $report;
    }

    public function test_visible_to_onduty_next_guru(): void
    {
        $rep = $this->createContinuableReport('Lanjut-1');
        $this->actingAs($this->guru('f7s3_guru_b'))->get('/guru')->assertStatus(200)->assertSee('Lanjutan dari Hari Sebelumnya');
        $this->actingAs($this->guru('f7s3_guru_b'))->get('/guru/laporan')->assertStatus(200)->assertSee('Lanjutan dari Hari Sebelumnya');
        $this->actingAs($this->guru('f7s3_guru_b'))->get('/guru/laporan/'.$rep->id)->assertStatus(200)->assertSee('Ambil Alih Laporan');
    }

    public function test_offduty_cannot_see_or_takeover(): void
    {
        $rep = $this->createContinuableReport('Lanjut-off');
        // A2: tak-berhak -> 404 (samarkan eksistensi), bukan 403.
        $this->actingAs($this->guru('f7s3_guru_d'))->get('/guru/laporan/'.$rep->id)->assertStatus(404);
        $this->actingAs($this->guru('f7s3_guru_d'))
            ->post('/guru/laporan/'.$rep->id.'/takeover')
            ->assertSessionHas('error');
        $this->assertEquals($this->teacherOf('f7s3_guru_a')->id, (int) $rep->fresh()->assigned_teacher_id);
    }

    public function test_takeover_moves_assignment_keeps_history(): void
    {
        $rep = $this->createContinuableReport('Lanjut-ok');
        $teacherB = $this->teacherOf('f7s3_guru_b');
        $this->actingAs($this->guru('f7s3_guru_b'))
            ->post('/guru/laporan/'.$rep->id.'/takeover')
            ->assertRedirect()
            ->assertSessionHas('success', 'Laporan berhasil diambil alih.');
        $fresh = $rep->fresh();
        $this->assertEquals($teacherB->id, (int) $fresh->assigned_teacher_id);
        $this->assertEquals('dalam_penanganan', $fresh->status);
        $this->assertEquals('Deskripsi asli Lanjut-ok', $fresh->description);
        $histories = ReportHistory::where('report_id', $rep->id)->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(2, $histories->count());
        $takeover = $histories->where('to_status', 'dalam_penanganan')->where('from_status', 'dalam_penanganan')->first();
        $this->assertNotNull($takeover);
        $this->assertEquals('claimed', $takeover->action);
        $this->assertEquals($this->guru('f7s3_guru_b')->id, (int) $takeover->user_id);
        // Guru lama bisa akses? tidak lagi sebagai pemilik -> 404 (A2).
        $this->actingAs($this->guru('f7s3_guru_a'))->get('/guru/laporan/'.$rep->id)->assertStatus(404);
        $this->actingAs($this->guru('f7s3_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/takeover')
            ->assertSessionHas('error', 'Laporan sudah diambil oleh Guru Piket lain.');
        // Guru baru dapat complete via Step 2
        $this->actingAs($this->guru('f7s3_guru_b'))
            ->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'Selesai oleh B.'])
            ->assertSessionHas('success');
        $this->assertEquals('selesai', $rep->fresh()->status);
    }

    public function test_concurrent_takeover_single_winner(): void
    {
        $rep = $this->createContinuableReport('Lanjut-race');
        $this->actingAs($this->guru('f7s3_guru_b'))->post('/guru/laporan/'.$rep->id.'/takeover')->assertSessionHas('success');
        $winner = (int) $rep->fresh()->assigned_teacher_id;
        $this->assertEquals($this->teacherOf('f7s3_guru_b')->id, $winner);
        $this->actingAs($this->guru('f7s3_guru_c'))
            ->post('/guru/laporan/'.$rep->id.'/takeover')
            ->assertSessionHas('error', 'Laporan sudah diambil oleh Guru Piket lain.');
        $this->assertEquals($winner, (int) $rep->fresh()->assigned_teacher_id);
        $this->assertEquals(1, ReportHistory::where('report_id', $rep->id)->where('action', 'claimed')->where('from_status', 'dalam_penanganan')->count());
    }

    public function test_status_protection(): void
    {
        // menunggu bukan takeover
        $st = Student::where('nis', 'F7S3NIS001')->firstOrFail();
        $cat = ReportCategory::where('name', 'TEST_Kat_F7S3')->firstOrFail();
        $waiting = Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => 'W', 'description' => 'd', 'location' => 'l',
            'priority' => $cat->priority, 'status' => 'menunggu',
        ]);
        $this->actingAs($this->guru('f7s3_guru_b'))->post('/guru/laporan/'.$waiting->id.'/takeover')->assertSessionHas('error');
        $waiting->delete();

        // selesai / diteruskan tidak bisa takeover
        $rep = $this->createContinuableReport('Final-x');
        $rep->update(['status' => 'selesai']);
        $this->actingAs($this->guru('f7s3_guru_b'))->post('/guru/laporan/'.$rep->id.'/takeover')->assertSessionHas('error');
        $rep->update(['status' => 'diteruskan']);
        $this->actingAs($this->guru('f7s3_guru_b'))->post('/guru/laporan/'.$rep->id.'/takeover')->assertSessionHas('error');
    }
}




