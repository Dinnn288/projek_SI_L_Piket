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

class NotificationKepalaSekolahReturnStep6Test extends TestCase
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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F9S6'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F9S6')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F9S6', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F9S6'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswa = $mkUser('f9s6_siswa', 'Siswa F9S6', 'siswa');
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'F9S6NIS001', 'class_id' => $class->id]);
        }
        $guruA = $mkUser('f9s6_guru_a', 'Guru A F9S6', 'guru');
        if (! Teacher::where('user_id', $guruA->id)->exists()) {
            Teacher::create(['user_id' => $guruA->id, 'nip' => 'F9S6NIP_A']);
        }
        $guruB = $mkUser('f9s6_guru_b', 'Guru B F9S6', 'guru');
        if (! Teacher::where('user_id', $guruB->id)->exists()) {
            Teacher::create(['user_id' => $guruB->id, 'nip' => 'F9S6NIP_B']);
        }
        $kepsek = $mkUser('f9s6_kepsek', 'Kepsek F9S6', 'kepala_sekolah');

        $this->ids = [
            'major' => $major->id, 'class' => $class->id, 'cat' => $cat->id,
            'siswa' => $siswa->id, 'guruA' => $guruA->id, 'guruB' => UserBaru::where('username', 'f9s6_guru_b')->value('id'),
            'kepsek' => $kepsek->id,
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
                $guruIds = UserBaru::whereIn('username', ['f9s6_guru_a', 'f9s6_guru_b'])->pluck('id');
                $teacherIds = Teacher::whereIn('user_id', $guruIds)->pluck('id');
                if ($teacherIds->isNotEmpty()) DB::table('duty_schedules')->whereIn('teacher_id', $teacherIds)->delete();
                Teacher::whereIn('user_id', $guruIds)->delete();
                UserBaru::whereIn('id', [$this->ids['siswa'] ?? 0])->delete();
                UserBaru::whereIn('username', ['f9s6_guru_a', 'f9s6_guru_b'])->delete();
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

    private function createForwardedReport(string $title, string $teacherNip = 'F9S6NIP_A'): Report
    {
        $st = Student::where('nis', 'F9S6NIS001')->firstOrFail();
        $cat = ReportCategory::findOrFail($this->ids['cat']);
        $teacher = Teacher::where('nip', $teacherNip)->firstOrFail();
        $guruUser = UserBaru::where('id', $teacher->user_id)->firstOrFail();

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

    public function test_return_creates_guru_notification(): void
    {
        $rep = $this->createForwardedReport('R-ok');
        $guruAId = (int) UserBaru::where('username', 'f9s6_guru_a')->value('id');
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'Mohon tangani kembali.'])
            ->assertSessionHas('success');
        $fresh = $rep->fresh();
        $this->assertEquals('dalam_penanganan', $fresh->status);
        $this->assertEquals('Deskripsi asli R-ok', $fresh->description);
        $this->assertNotNull(ReportHistory::where('report_id', $rep->id)->where('action', 'returned')->first());

        $notif = Notification::where('report_id', $rep->id)->where('title', 'Laporan Dikembalikan')->first();
        $this->assertNotNull($notif);
        $this->assertEquals($guruAId, (int) $notif->user_id);
        $this->assertEquals('Laporan dikembalikan oleh Kepala Sekolah untuk ditindaklanjuti kembali.', $notif->message);
        $this->assertFalse((bool) $notif->is_read);
        // Bukan guru lain / siswa / kepsek
        $guruBId = (int) UserBaru::where('username', 'f9s6_guru_b')->value('id');
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Dikembalikan')->where('user_id', $guruBId)->count());
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai')->count());
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Selesai oleh Kepala Sekolah')->count());
        // UI guru menampilkan
        $guruA = UserBaru::where('username', 'f9s6_guru_a')->firstOrFail();
        $this->actingAs($guruA)->get('/guru')->assertStatus(200)->assertSee('Laporan Dikembalikan');
    }

    public function test_takeover_recipient_is_current_assignee(): void
    {
        // assigned saat ini = Guru B (simulasi pasca-takeover)
        $rep = $this->createForwardedReport('R-take', 'F9S6NIP_B');
        $guruBId = (int) UserBaru::where('username', 'f9s6_guru_b')->value('id');
        $guruAId = (int) UserBaru::where('username', 'f9s6_guru_a')->value('id');
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'Kembali.'])
            ->assertSessionHas('success');
        $this->assertEquals(1, Notification::where('report_id', $rep->id)->where('title', 'Laporan Dikembalikan')->where('user_id', $guruBId)->count());
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Dikembalikan')->where('user_id', $guruAId)->count());
    }

    public function test_invalid_status_and_note_rejected(): void
    {
        foreach (['menunggu', 'dalam_penanganan', 'selesai'] as $st) {
            $rep = $this->createForwardedReport('R-inv-'.$st);
            $rep->update(['status' => $st]);
            $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'x'])->assertSessionHas('error');
            $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Dikembalikan')->count());
        }
        $rep2 = $this->createForwardedReport('R-note');
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep2->id.'/return', ['note' => ''])->assertSessionHasErrors('note');
        $this->assertEquals('diteruskan', $rep2->fresh()->status);
        $this->assertEquals(0, Notification::where('report_id', $rep2->id)->where('title', 'Laporan Dikembalikan')->count());
        $this->assertEquals(0, ReportHistory::where('report_id', $rep2->id)->where('action', 'returned')->count());
    }

    public function test_duplicate_return_rejected(): void
    {
        $rep = $this->createForwardedReport('R-dup');
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'ok']);
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'lagi'])
            ->assertSessionHas('error');
        $this->assertEquals(1, Notification::where('report_id', $rep->id)->where('title', 'Laporan Dikembalikan')->count());
        $this->assertEquals(1, ReportHistory::where('report_id', $rep->id)->where('action', 'returned')->count());
    }

    public function test_multi_return_allows_second_event(): void
    {
        $rep = $this->createForwardedReport('R-multi');
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'kembali 1']);
        // Siklus: guru forward lagi -> kepsek return lagi
        $rep->refresh();
        $rep->update(['status' => 'diteruskan']);
        ReportHistory::create([
            'report_id' => $rep->id, 'user_id' => UserBaru::where('username', 'f9s6_guru_a')->value('id'),
            'action' => 'forwarded', 'from_status' => 'dalam_penanganan',
            'to_status' => 'diteruskan', 'note' => 'Forward lagi.',
        ]);
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'kembali 2'])->assertSessionHas('success');
        $this->assertEquals(2, Notification::where('report_id', $rep->id)->where('title', 'Laporan Dikembalikan')->count());
        $this->assertEquals(2, ReportHistory::where('report_id', $rep->id)->where('action', 'returned')->count());
    }

    public function test_security_roles_guest_and_manipulation(): void
    {
        $rep = $this->createForwardedReport('R-sec');
        $siswa = UserBaru::where('username', 'f9s6_siswa')->firstOrFail();
        $guru = UserBaru::where('username', 'f9s6_guru_a')->firstOrFail();
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $this->actingAs($siswa)->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'x'])->assertStatus(403);
        $this->actingAs($guru)->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'x'])->assertStatus(403);
        if ($admin) $this->actingAs($admin)->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'x'])->assertStatus(403);
        $this->refreshApplication();
        $this->reconnect();
        Storage::fake('local');
        $this->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'x'])->assertRedirect('/login');
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Dikembalikan')->count());

        $rep2 = $this->createForwardedReport('R-manip');
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep2->id.'/return', [
            'note' => 'ok', 'user_id' => 999, 'teacher_id' => 999, 'assigned_teacher_id' => 999,
            'status' => 'selesai', 'description' => 'HACK',
        ])->assertSessionHas('success');
        $fresh = $rep2->fresh();
        $this->assertEquals('dalam_penanganan', $fresh->status);
        $this->assertEquals('Deskripsi asli R-manip', $fresh->description);
    }
}




