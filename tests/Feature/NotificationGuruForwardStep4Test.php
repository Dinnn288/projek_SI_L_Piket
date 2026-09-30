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

class NotificationGuruForwardStep4Test extends TestCase
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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F9S4'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F9S4')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F9S4', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F9S4'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswa = $mkUser('f9s4_siswa', 'Siswa F9S4', 'siswa');
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'F9S4NIS001', 'class_id' => $class->id]);
        }
        $today = GuruDutyService::todayDay() ?? 'senin';
        foreach ([['f9s4_guru_a', 'F9S4NIP_A'], ['f9s4_guru_b', 'F9S4NIP_B']] as [$u, $nip]) {
            $gu = $mkUser($u, "Guru $u", 'guru');
            $teacher = Teacher::where('user_id', $gu->id)->first()
                ?: Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
                DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
            }
        }
        $kepsek = $mkUser('f9s4_kepsek', 'Kepsek F9S4', 'kepala_sekolah');

        $this->ids = [
            'major' => $major->id, 'class' => $class->id, 'cat' => $cat->id,
            'siswa' => $siswa->id, 'kepsek' => $kepsek->id,
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
                $guruIds = UserBaru::whereIn('username', ['f9s4_guru_a', 'f9s4_guru_b'])->pluck('id');
                $teacherIds = Teacher::whereIn('user_id', $guruIds)->pluck('id');
                if ($teacherIds->isNotEmpty()) DutySchedule::whereIn('teacher_id', $teacherIds)->delete();
                Teacher::whereIn('user_id', $guruIds)->delete();
                UserBaru::whereIn('id', [$this->ids['siswa'] ?? 0])->delete();
                UserBaru::whereIn('username', ['f9s4_guru_a', 'f9s4_guru_b'])->delete();
                if (isset($this->ids['kepsek'])) UserBaru::whereKey($this->ids['kepsek'])->delete();
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
        $st = Student::where('nis', 'F9S4NIS001')->firstOrFail();
        $cat = ReportCategory::findOrFail($this->ids['cat']);
        $teacher = Teacher::where('nip', 'F9S4NIP_A')->firstOrFail();

        return Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'Deskripsi asli '.$title,
            'location' => 'Kelas - lokasi', 'priority' => $cat->priority,
            'status' => 'dalam_penanganan', 'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_forward_creates_kepsek_notification(): void
    {
        $rep = $this->createHandledReport('F-ok');
        $activeKepsekIds = UserBaru::where('role', 'kepala_sekolah')->where('status', 'aktif')->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->assertContains((int) $this->ids['kepsek'], $activeKepsekIds);
        $this->actingAs($this->guru('f9s4_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'Butuh tindak lanjut.'])
            ->assertSessionHas('success');
        $this->assertEquals('diteruskan', $rep->fresh()->status);
        $this->assertNotNull(ReportHistory::where('report_id', $rep->id)->where('action', 'forwarded')->first());

        // Kepsek uji harus menerima; sistem mendukung multi-kepsek sehingga
        // total = jumlah kepsek aktif (subset, bukan tepat 1 global).
        $notif = Notification::where('report_id', $rep->id)->where('title', 'Laporan Diteruskan')->where('user_id', $this->ids['kepsek'])->first();
        $this->assertNotNull($notif);
        $this->assertEquals('Ada laporan yang diteruskan oleh Guru Piket dan memerlukan tindak lanjut.', $notif->message);
        $this->assertFalse((bool) $notif->is_read);
        $this->assertEquals(count($activeKepsekIds), Notification::where('report_id', $rep->id)->where('title', 'Laporan Diteruskan')->count());
        $this->assertEquals('Deskripsi asli F-ok', $rep->fresh()->description);
        // Tidak ada notifikasi Laporan Selesai untuk forward
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai')->count());

        $kepsek = UserBaru::find($this->ids['kepsek']);
        $this->actingAs($kepsek)->get('/kepala-sekolah')->assertStatus(200)->assertSee('Laporan Diteruskan');
    }

    public function test_failed_forward_no_notification(): void
    {
        $rep = $this->createHandledReport('F-fail');
        // Guru B bukan pemilik -> gagal
        $this->actingAs($this->guru('f9s4_guru_b'))->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'x'])->assertSessionHas('error');
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Diteruskan')->count());
        // Note kosong -> validasi gagal
        $this->actingAs($this->guru('f9s4_guru_a'))->post('/guru/laporan/'.$rep->id.'/forward', ['note' => ''])->assertSessionHasErrors('note');
        $this->assertEquals('dalam_penanganan', $rep->fresh()->status);
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Diteruskan')->count());
        // Status bukan dalam_penanganan -> gagal
        $rep->update(['status' => 'selesai']);
        $this->actingAs($this->guru('f9s4_guru_a'))->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'x'])->assertSessionHas('error');
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Diteruskan')->count());
    }

    public function test_no_duplicate_on_retry(): void
    {
        $rep = $this->createHandledReport('F-dup');
        $expected = UserBaru::where('role', 'kepala_sekolah')->where('status', 'aktif')->count();
        $this->actingAs($this->guru('f9s4_guru_a'))->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'ok']);
        $this->actingAs($this->guru('f9s4_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'lagi'])
            ->assertSessionHas('error');
        $this->assertEquals($expected, Notification::where('report_id', $rep->id)->where('title', 'Laporan Diteruskan')->count());
        $this->assertEquals(1, ReportHistory::where('report_id', $rep->id)->where('action', 'forwarded')->count());
        // Kepsek uji tepat 1
        $this->assertEquals(1, Notification::where('report_id', $rep->id)->where('title', 'Laporan Diteruskan')->where('user_id', $this->ids['kepsek'])->count());
    }

    public function test_security_roles_guest(): void
    {
        $rep = $this->createHandledReport('F-sec');
        $siswa = UserBaru::where('username', 'f9s4_siswa')->firstOrFail();
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $kepsek = UserBaru::find($this->ids['kepsek']);
        $this->actingAs($siswa)->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'x'])->assertStatus(403);
        if ($admin) $this->actingAs($admin)->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'x'])->assertStatus(403);
        $this->actingAs($kepsek)->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'x'])->assertStatus(403);
        $this->refreshApplication();
        $this->reconnect();
        Storage::fake('local');
        $this->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'x'])->assertRedirect('/login');
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Diteruskan')->count());
    }
}




