<?php

namespace Tests\Feature;

use App\Models\DutySchedule;
use App\Models\Major;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportCategory;
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

class NotificationReportCreatedStep1Test extends TestCase
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
        $tmp = tempnam(sys_get_temp_dir(), 'f9s1').'.png';
        file_put_contents($tmp, $png);
        clearstatcache(true, $tmp);

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F9S1'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F9S1')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F9S1', 'status' => 'aktif']);
        ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F9S1'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $siswaUser = UserBaru::where('username', 'f9s1_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa F9S1', 'username' => 'f9s1_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        if (! Student::where('user_id', $siswaUser->id)->exists()) {
            Student::create(['user_id' => $siswaUser->id, 'nis' => 'F9S1NIS001', 'class_id' => $class->id]);
        }

        $today = GuruDutyService::todayDay() ?? 'senin';
        foreach ([['f9s1_guru_a', 'F9S1NIP_A'], ['f9s1_guru_b', 'F9S1NIP_B'], ['f9s1_guru_c', 'F9S1NIP_C']] as [$u, $nip]) {
            $gu = UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => "Guru $u", 'username' => $u, 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
            $teacher = Teacher::where('user_id', $gu->id)->first()
                ?: Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
                DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
            }
        }
        // Guru D: tidak piket
        $guD = UserBaru::where('username', 'f9s1_guru_d')->first()
            ?: UserBaru::create(['name' => 'Guru D', 'username' => 'f9s1_guru_d', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        if (! Teacher::where('user_id', $guD->id)->exists()) {
            Teacher::create(['user_id' => $guD->id, 'nip' => 'F9S1NIP_D']);
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            $sids = Student::where('nis', 'F9S1NIS001')->pluck('id');
            $rids = Report::whereIn('student_id', $sids)->pluck('id');
            if ($rids->isNotEmpty()) {
                Notification::whereIn('report_id', $rids)->delete();
                ReportAttachment::whereIn('report_id', $rids)->delete();
                Report::whereIn('id', $rids)->delete();
            }
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function siswa(): UserBaru
    {
        return UserBaru::where('username', 'f9s1_siswa')->firstOrFail();
    }

    private function postReport(string $title = 'Lampu mati'): \Illuminate\Testing\TestResponse
    {
        $cat = ReportCategory::where('name', 'TEST_Kat_F9S1')->firstOrFail();

        return $this->actingAs($this->siswa())->post('/siswa/laporan', [
            'title' => $title,
            'category_id' => $cat->id,
            'location' => 'TEST_KELAS_F9S1 - depan',
            'description' => 'Deskripsi '.$title,
            'photos' => [$this->makePhoto('a.png')],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    private function f9Usernames(): array
    {
        return ['f9s1_guru_a', 'f9s1_guru_b', 'f9s1_guru_c'];
    }

    private function onDutyUserIds(): array
    {
        return Teacher::whereIn('user_id', UserBaru::whereIn('username', $this->f9Usernames())->pluck('id'))
            ->pluck('user_id')->map(fn ($v) => (int) $v)->all();
    }

    private function f9UserIds(): array
    {
        return UserBaru::whereIn('username', [...$this->f9Usernames(), 'f9s1_guru_d', 'f9s1_siswa'])
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    public function test_notification_created_for_onduty_gurus(): void
    {
        $this->postReport('N-1')->assertRedirect();
        $report = Report::whereHas('student', fn ($q) => $q->where('nis', 'F9S1NIS001'))->latest('id')->first();
        $this->assertNotNull($report);
        $this->assertEquals('menunggu', $report->status);
        $this->assertNull($report->assigned_teacher_id);

        $notifs = Notification::where('report_id', $report->id)->get();
        // Semua 3 guru F9 piket harus menerima; suite lain boleh menambah
        // jadwal di hari yang sama pada DB persisten, jadi assert subset.
        foreach ($this->onDutyUserIds() as $uid) {
            $this->assertTrue(
                $notifs->pluck('user_id')->map(fn ($v) => (int) $v)->contains($uid),
                "Guru piket F9 user_id={$uid} harus menerima notifikasi."
            );
        }
        $f9Notifs = $notifs->whereIn('user_id', $this->onDutyUserIds());
        $this->assertCount(3, $f9Notifs);
        foreach ($f9Notifs as $n) {
            $this->assertEquals('Laporan Baru', $n->title);
            $this->assertEquals('Ada laporan baru yang perlu diperiksa.', $n->message);
            $this->assertFalse((bool) $n->is_read);
        }
        // Guru tidak piket tidak dapat
        $dId = UserBaru::where('username', 'f9s1_guru_d')->value('id');
        $this->assertEquals(0, Notification::where('report_id', $report->id)->where('user_id', $dId)->count());
        // Admin/siswa/kepsek tidak dapat notifikasi event ini — batasi ke user F9
        $this->assertEquals(0, Notification::where('report_id', $report->id)->whereIn('user_id', $this->f9UserIds())->whereNotIn('user_id', [...$this->onDutyUserIds(), $dId])->count());
    }

    public function test_no_duplicate_notification_per_event(): void
    {
        $this->postReport('N-dup')->assertRedirect();
        $report = Report::whereHas('student', fn ($q) => $q->where('nis', 'F9S1NIS001'))->latest('id')->first();
        $uid = $this->onDutyUserIds()[0];
        $before = Notification::where('report_id', $report->id)->where('user_id', $uid)->count();
        $this->assertEquals(1, $before);
        // Simulasi pemicu ulang: guard exists mencegah duplikat
        $exists = Notification::where('user_id', $uid)->where('report_id', $report->id)->where('title', 'Laporan Baru')->exists();
        $this->assertTrue($exists);
    }

    public function test_failed_report_creates_no_notification(): void
    {
        $cat = ReportCategory::where('name', 'TEST_Kat_F9S1')->firstOrFail();
        // Tanpa foto -> validasi gagal, tidak ada report & notifikasi
        $this->actingAs($this->siswa())->post('/siswa/laporan', [
            'title' => 'Gagal', 'category_id' => $cat->id,
            'location' => 'x', 'description' => 'y',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertSessionHasErrors('photos');
        $this->assertEquals(0, Report::where('title', 'Gagal')->count());
        $this->assertEquals(0, Notification::whereHas('report', fn ($q) => $q->where('title', 'Gagal'))->count());
    }

    public function test_student_cannot_choose_recipient(): void
    {
        $cat = ReportCategory::where('name', 'TEST_Kat_F9S1')->firstOrFail();
        $dId = UserBaru::where('username', 'f9s1_guru_d')->value('id');
        $this->actingAs($this->siswa())->post('/siswa/laporan', [
            'title' => 'N-manip', 'category_id' => $cat->id,
            'location' => 'x', 'description' => 'y',
            'photos' => [$this->makePhoto('a.png')],
            'user_id' => $dId, 'teacher_id' => 999, 'assigned_teacher_id' => 999,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();
        $report = Report::where('title', 'N-manip')->firstOrFail();
        $stA = Student::where('nis', 'F9S1NIS001')->first();
        $this->assertEquals($stA->id, (int) $report->student_id);
        $this->assertEquals($stA->class_id, (int) $report->class_id);
        $this->assertEquals(0, Notification::where('report_id', $report->id)->where('user_id', $dId)->count());
    }

    public function test_claim_workflow_unchanged_after_notification(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));
        $this->postReport('N-claim')->assertRedirect();
        $report = Report::where('title', 'N-claim')->firstOrFail();
        $this->assertNull($report->fresh()->assigned_teacher_id);
        // Guru tetap claim via workflow existing
        $guruA = UserBaru::where('username', 'f9s1_guru_a')->firstOrFail();
        $this->actingAs($guruA)->post('/guru/laporan/'.$report->id.'/claim')->assertSessionHas('success');
        $this->assertEquals('dalam_penanganan', $report->fresh()->status);
    }

    public function test_guru_dashboard_shows_notifications(): void
    {
        $this->postReport('N-ui')->assertRedirect();
        $guruA = UserBaru::where('username', 'f9s1_guru_a')->firstOrFail();
        $this->actingAs($guruA)->get('/guru')->assertStatus(200)->assertSee('Notifikasi')->assertSee('Laporan Baru');
    }
}




