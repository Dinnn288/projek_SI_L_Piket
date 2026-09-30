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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationGuruCompleteStep3Test extends TestCase
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
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F9S3'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F9S3')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F9S3', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F9S3'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswa = $mkUser('f9s3_siswa', 'Siswa F9S3', 'siswa');
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'F9S3NIS001', 'class_id' => $class->id]);
        }
        $today = GuruDutyService::todayDay() ?? 'senin';
        foreach ([['f9s3_guru_a', 'F9S3NIP_A'], ['f9s3_guru_b', 'F9S3NIP_B']] as [$u, $nip]) {
            $gu = $mkUser($u, "Guru $u", 'guru');
            $teacher = Teacher::where('user_id', $gu->id)->first()
                ?: Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
                DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
            }
        }

        $this->ids = [
            'major' => $major->id, 'class' => $class->id, 'cat' => $cat->id,
            'siswa' => $siswa->id,
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $sids = Student::whereIn('user_id', [$this->ids['siswa'] ?? 0])->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::whereIn('user_id', [$this->ids['siswa'] ?? 0])->delete();
                $guruIds = UserBaru::whereIn('username', ['f9s3_guru_a', 'f9s3_guru_b'])->pluck('id');
                $teacherIds = Teacher::whereIn('user_id', $guruIds)->pluck('id');
                if ($teacherIds->isNotEmpty()) DutySchedule::whereIn('teacher_id', $teacherIds)->delete();
                Teacher::whereIn('user_id', $guruIds)->delete();
                UserBaru::whereIn('id', [$this->ids['siswa'] ?? 0])->delete();
                UserBaru::whereIn('username', ['f9s3_guru_a', 'f9s3_guru_b'])->delete();
                if (isset($this->ids['cat'])) ReportCategory::whereKey($this->ids['cat'])->delete();
                if (isset($this->ids['class'])) SchoolClass::whereKey($this->ids['class'])->delete();
                if (isset($this->ids['major'])) Major::whereKey($this->ids['major'])->delete();
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

    private function createHandledReport(string $title): Report
    {
        $st = Student::where('nis', 'F9S3NIS001')->firstOrFail();
        $cat = ReportCategory::findOrFail($this->ids['cat']);
        $teacher = Teacher::where('nip', 'F9S3NIP_A')->firstOrFail();

        return Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'Deskripsi asli '.$title,
            'location' => 'Kelas - lokasi', 'priority' => $cat->priority,
            'status' => 'dalam_penanganan', 'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_complete_creates_notification(): void
    {
        $rep = $this->createHandledReport('C-ok');
        $ownerId = (int) UserBaru::where('username', 'f9s3_siswa')->value('id');
        $this->actingAs($this->guru('f9s3_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'Sudah diperbaiki.'])
            ->assertSessionHas('success');
        $fresh = $rep->fresh();
        $this->assertEquals('selesai', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertNotNull(ReportHistory::where('report_id', $rep->id)->where('action', 'completed')->first());
        $notif = Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai')->first();
        $this->assertNotNull($notif);
        $this->assertEquals($ownerId, (int) $notif->user_id);
        $this->assertEquals('Laporan Anda telah selesai ditangani oleh Guru Piket.', $notif->message);
        $this->assertFalse((bool) $notif->is_read);
        $this->assertEquals('Deskripsi asli C-ok', $fresh->description);
        // Dashboard siswa menampilkan
        $siswa = UserBaru::where('username', 'f9s3_siswa')->firstOrFail();
        $this->actingAs($siswa)->get('/siswa')->assertStatus(200)->assertSee('Laporan Selesai');
    }

    public function test_forward_creates_no_complete_notification(): void
    {
        $rep = $this->createHandledReport('F-noc');
        $this->actingAs($this->guru('f9s3_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'Butuh kepsek.'])
            ->assertSessionHas('success');
        $this->assertEquals('diteruskan', $rep->fresh()->status);
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai')->count());
        $this->assertNotNull(ReportHistory::where('report_id', $rep->id)->where('action', 'forwarded')->first());
    }

    public function test_duplicate_and_invalid_rejected(): void
    {
        $rep = $this->createHandledReport('C-dup');
        $this->actingAs($this->guru('f9s3_guru_a'))->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'ok']);
        $this->actingAs($this->guru('f9s3_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'lagi'])
            ->assertSessionHas('error');
        $this->assertEquals(1, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai')->count());
        $this->assertEquals(1, ReportHistory::where('report_id', $rep->id)->where('action', 'completed')->count());
        // Note kosong / terlalu panjang ditolak tanpa notifikasi
        $rep2 = $this->createHandledReport('C-note');
        $this->actingAs($this->guru('f9s3_guru_a'))->post('/guru/laporan/'.$rep2->id.'/complete', ['note' => ''])->assertSessionHasErrors('note');
        $this->actingAs($this->guru('f9s3_guru_a'))->post('/guru/laporan/'.$rep2->id.'/complete', ['note' => str_repeat('a', 2001)])->assertSessionHasErrors('note');
        $this->assertEquals('dalam_penanganan', $rep2->fresh()->status);
        $this->assertEquals(0, Notification::where('report_id', $rep2->id)->where('title', 'Laporan Selesai')->count());
    }

    public function test_ownership_and_status_protection(): void
    {
        $rep = $this->createHandledReport('C-own');
        // Guru B tidak bisa complete milik A
        $this->actingAs($this->guru('f9s3_guru_b'))->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'hack'])->assertSessionHas('error');
        $this->assertEquals('dalam_penanganan', $rep->fresh()->status);
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->count());
        // Status menunggu/diteruskan/selesai ditolak
        foreach (['menunggu', 'diteruskan', 'selesai'] as $st) {
            $clone = $this->createHandledReport('C-st-'.$st);
            $clone->update(['status' => $st]);
            $this->actingAs($this->guru('f9s3_guru_a'))->post('/guru/laporan/'.$clone->id.'/complete', ['note' => 'x'])->assertSessionHas('error');
            $this->assertEquals(0, Notification::where('report_id', $clone->id)->where('title', 'Laporan Selesai')->count());
        }
    }

    public function test_security_roles_guest(): void
    {
        $rep = $this->createHandledReport('C-sec');
        $siswa = UserBaru::where('username', 'f9s3_siswa')->firstOrFail();
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $kepsek = UserBaru::where('role', 'kepala_sekolah')->where('status', 'aktif')->first();
        $this->actingAs($siswa)->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
        if ($admin) $this->actingAs($admin)->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
        if ($kepsek) $this->actingAs($kepsek)->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
        $this->refreshApplication();
        $this->reconnect();
        Storage::fake('local');
        $this->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertRedirect('/login');
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai')->count());
    }
}




