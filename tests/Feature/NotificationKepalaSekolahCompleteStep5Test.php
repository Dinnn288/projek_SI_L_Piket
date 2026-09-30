<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationKepalaSekolahCompleteStep5Test extends TestCase
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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F9S5'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F9S5')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F9S5', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F9S5'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswa = $mkUser('f9s5_siswa', 'Siswa F9S5', 'siswa');
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'F9S5NIS001', 'class_id' => $class->id]);
        }
        $guru = $mkUser('f9s5_guru', 'Guru F9S5', 'guru');
        if (! Teacher::where('user_id', $guru->id)->exists()) {
            Teacher::create(['user_id' => $guru->id, 'nip' => 'F9S5NIP']);
        }
        $kepsek = $mkUser('f9s5_kepsek', 'Kepsek F9S5', 'kepala_sekolah');

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
                $guruIds = UserBaru::whereIn('username', ['f9s5_guru'])->pluck('id');
                $teacherIds = Teacher::whereIn('user_id', $guruIds)->pluck('id');
                if ($teacherIds->isNotEmpty()) DB::table('duty_schedules')->whereIn('teacher_id', $teacherIds)->delete();
                Teacher::whereIn('user_id', $guruIds)->delete();
                UserBaru::whereIn('id', [$this->ids['siswa'] ?? 0])->delete();
                UserBaru::whereIn('username', ['f9s5_guru'])->delete();
                if (isset($this->ids['kepsek'])) UserBaru::whereKey($this->ids['kepsek'])->delete();
                if (isset($this->ids['cat'])) ReportCategory::whereKey($this->ids['cat'])->delete();
                if (isset($this->ids['class'])) SchoolClass::whereKey($this->ids['class'])->delete();
                if (isset($this->ids['major'])) Major::whereKey($this->ids['major'])->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function kepsek(): UserBaru
    {
        return UserBaru::findOrFail($this->ids['kepsek']);
    }

    private function createForwardedReport(string $title): Report
    {
        $st = Student::where('nis', 'F9S5NIS001')->firstOrFail();
        $cat = ReportCategory::findOrFail($this->ids['cat']);
        $teacher = Teacher::where('nip', 'F9S5NIP')->firstOrFail();
        $guruUser = UserBaru::where('username', 'f9s5_guru')->firstOrFail();

        $report = Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'Deskripsi asli '.$title,
            'location' => 'Kelas - lokasi', 'priority' => $cat->priority,
            'status' => 'diteruskan', 'assigned_teacher_id' => $teacher->id, 'assigned_at' => now(),
        ]);
        ReportHistory::create([
            'report_id' => $report->id, 'user_id' => $guruUser->id,
            'action' => 'forwarded', 'from_status' => 'dalam_penanganan',
            'to_status' => 'diteruskan', 'note' => 'Alasan forward.',
        ]);

        return $report;
    }

    public function test_complete_creates_notification(): void
    {
        $rep = $this->createForwardedReport('C-ok');
        $ownerId = (int) UserBaru::where('username', 'f9s5_siswa')->value('id');
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'Sudah ditindaklanjuti.'])
            ->assertSessionHas('success');
        $fresh = $rep->fresh();
        $this->assertEquals('selesai', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertNotNull(ReportHistory::where('report_id', $rep->id)->where('action', 'completed')->first());
        $notif = Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai oleh Kepala Sekolah')->first();
        $this->assertNotNull($notif);
        $this->assertEquals($ownerId, (int) $notif->user_id);
        $this->assertEquals('Laporan Anda telah selesai ditindaklanjuti oleh Kepala Sekolah.', $notif->message);
        $this->assertFalse((bool) $notif->is_read);
        $this->assertEquals('Deskripsi asli C-ok', $fresh->description);
        // Berbeda dari Step 3 (Guru): tidak ada notifikasi Laporan Selesai guru
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai')->count());
        // Dashboard siswa menampilkan
        $siswa = UserBaru::where('username', 'f9s5_siswa')->firstOrFail();
        $this->actingAs($siswa)->get('/siswa')->assertStatus(200)->assertSee('Laporan Selesai oleh Kepala Sekolah');
    }

    public function test_invalid_status_and_note_rejected(): void
    {
        foreach (['menunggu', 'dalam_penanganan', 'selesai'] as $st) {
            $rep = $this->createForwardedReport('C-inv-'.$st);
            $rep->update(['status' => $st]);
            $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertSessionHas('error');
            $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai oleh Kepala Sekolah')->count());
        }
        $rep2 = $this->createForwardedReport('C-note');
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep2->id.'/complete', ['note' => ''])->assertSessionHasErrors('note');
        $this->assertEquals('diteruskan', $rep2->fresh()->status);
        $this->assertEquals(0, Notification::where('report_id', $rep2->id)->where('title', 'Laporan Selesai oleh Kepala Sekolah')->count());
    }

    public function test_duplicate_complete_rejected(): void
    {
        $rep = $this->createForwardedReport('C-dup');
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'ok']);
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'lagi'])
            ->assertSessionHas('error');
        $this->assertEquals(1, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai oleh Kepala Sekolah')->count());
        $this->assertEquals(1, ReportHistory::where('report_id', $rep->id)->where('action', 'completed')->count());
    }

    public function test_return_produces_no_step5_notification(): void
    {
        $rep = $this->createForwardedReport('R-nostep5');
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'Kembali ke guru.'])
            ->assertSessionHas('success');
        $this->assertEquals('dalam_penanganan', $rep->fresh()->status);
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai oleh Kepala Sekolah')->count());
        $this->assertNotNull(ReportHistory::where('report_id', $rep->id)->where('action', 'returned')->first());
    }

    public function test_security_roles_guest(): void
    {
        $rep = $this->createForwardedReport('C-sec');
        $siswa = UserBaru::where('username', 'f9s5_siswa')->firstOrFail();
        $guru = UserBaru::where('username', 'f9s5_guru')->firstOrFail();
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $this->actingAs($siswa)->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
        $this->actingAs($guru)->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
        if ($admin) $this->actingAs($admin)->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
        $this->refreshApplication();
        $this->reconnect();
        Storage::fake('local');
        $this->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertRedirect('/login');
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai oleh Kepala Sekolah')->count());
    }

    public function test_manipulasi_diabaikan(): void
    {
        $rep = $this->createForwardedReport('C-manip');
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', [
            'note' => 'ok', 'user_id' => 999, 'status' => 'dalam_penanganan',
            'assigned_teacher_id' => 999, 'description' => 'HACK',
        ])->assertSessionHas('success');
        $fresh = $rep->fresh();
        $this->assertEquals('selesai', $fresh->status);
        $this->assertEquals('Deskripsi asli C-manip', $fresh->description);
    }
}




