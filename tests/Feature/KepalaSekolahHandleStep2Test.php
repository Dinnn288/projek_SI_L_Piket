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

class KepalaSekolahHandleStep2Test extends TestCase
{
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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F8S2'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F8S2')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F8S2', 'status' => 'aktif']);
        ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F8S2'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $siswaUser = UserBaru::where('username', 'f8s2_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa F8S2', 'username' => 'f8s2_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        if (! Student::where('user_id', $siswaUser->id)->exists()) {
            Student::create(['user_id' => $siswaUser->id, 'nis' => 'F8S2NIS001', 'class_id' => $class->id]);
        }
        $guruUser = UserBaru::where('username', 'f8s2_guru')->first()
            ?: UserBaru::create(['name' => 'Guru F8S2', 'username' => 'f8s2_guru', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        if (! Teacher::where('user_id', $guruUser->id)->exists()) {
            Teacher::create(['user_id' => $guruUser->id, 'nip' => 'F8S2NIP']);
        }
        if (! UserBaru::where('username', 'f8s2_kepsek')->exists()) {
            UserBaru::create(['name' => 'Kepsek F8S2', 'username' => 'f8s2_kepsek', 'password' => Hash::make('password123'), 'role' => 'kepala_sekolah', 'status' => 'aktif']);
        }
        if (! UserBaru::where('username', 'f8s2_nonaktif')->exists()) {
            UserBaru::create(['name' => 'Nonaktif F8S2', 'username' => 'f8s2_nonaktif', 'password' => Hash::make('password123'), 'role' => 'kepala_sekolah', 'status' => 'nonaktif']);
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            $sids = Student::where('nis', 'F8S2NIS001')->pluck('id');
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
        return UserBaru::where('username', 'f8s2_kepsek')->firstOrFail();
    }

    private function createForwardedReport(string $title, string $note = 'Alasan forward.'): Report
    {
        $st = Student::where('nis', 'F8S2NIS001')->firstOrFail();
        $cat = ReportCategory::where('name', 'TEST_Kat_F8S2')->firstOrFail();
        $teacher = Teacher::where('nip', 'F8S2NIP')->firstOrFail();
        $guruUser = UserBaru::where('username', 'f8s2_guru')->firstOrFail();
        $report = Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'Deskripsi asli '.$title,
            'location' => 'Kelas - lokasi', 'priority' => $cat->priority,
            'status' => 'diteruskan', 'assigned_teacher_id' => $teacher->id, 'assigned_at' => now(),
        ]);
        ReportHistory::create([
            'report_id' => $report->id, 'user_id' => $guruUser->id,
            'action' => 'forwarded', 'from_status' => 'dalam_penanganan',
            'to_status' => 'diteruskan', 'note' => $note,
        ]);

        return $report;
    }

    private function createStatusReport(string $title, string $status): Report
    {
        $st = Student::where('nis', 'F8S2NIS001')->firstOrFail();
        $cat = ReportCategory::where('name', 'TEST_Kat_F8S2')->firstOrFail();

        return Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'd', 'location' => 'l',
            'priority' => $cat->priority, 'status' => $status,
        ]);
    }

    public function test_auth_login_nonaktif_guest(): void
    {
        $this->post('/login', ['username' => 'f8s2_kepsek', 'password' => 'password123'])->assertRedirect('/kepala-sekolah');
        $this->refreshApplication();
        Storage::fake('local');
        $this->post('/login', ['username' => 'f8s2_nonaktif', 'password' => 'password123']);
        $this->assertGuest();
        $this->get('/kepala-sekolah')->assertRedirect('/login');
    }

    public function test_authorization_roles(): void
    {
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $siswa = UserBaru::where('username', 'f8s2_siswa')->first();
        $guru = UserBaru::where('username', 'f8s2_guru')->first();
        $rep = $this->createForwardedReport('Auth-1');
        if ($admin) $this->actingAs($admin)->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
        if ($siswa) $this->actingAs($siswa)->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
        if ($guru) $this->actingAs($guru)->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
    }

    public function test_complete_success(): void
    {
        $rep = $this->createForwardedReport('C-ok');
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'Sudah ditindaklanjuti.'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Laporan berhasil diselesaikan.');
        $fresh = $rep->fresh();
        $this->assertEquals('selesai', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertEquals('Deskripsi asli C-ok', $fresh->description);
        $hist = ReportHistory::where('report_id', $rep->id)->where('action', 'completed')->first();
        $this->assertNotNull($hist);
        $this->assertEquals('diteruskan', $hist->from_status);
        $this->assertEquals('selesai', $hist->to_status);
        $this->assertEquals('Sudah ditindaklanjuti.', $hist->note);
        $this->assertEquals($this->kepsek()->id, (int) $hist->user_id);
        // Tidak bisa complete dua kali
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'lagi'])
            ->assertSessionHas('error');
        $this->assertEquals(1, ReportHistory::where('report_id', $rep->id)->where('action', 'completed')->count());
    }

    public function test_complete_note_required(): void
    {
        $rep = $this->createForwardedReport('C-req');
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => ''])
            ->assertSessionHasErrors('note');
        $this->assertEquals('diteruskan', $rep->fresh()->status);
    }

    public function test_return_success_handoff(): void
    {
        $rep = $this->createForwardedReport('R-ok');
        $teacherId = (int) $rep->assigned_teacher_id;
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'Mohon tangani kembali.'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Laporan berhasil dikembalikan ke Guru Piket.');
        $fresh = $rep->fresh();
        $this->assertEquals('dalam_penanganan', $fresh->status);
        $this->assertEquals($teacherId, (int) $fresh->assigned_teacher_id);
        $this->assertEquals('Deskripsi asli R-ok', $fresh->description);
        $hist = ReportHistory::where('report_id', $rep->id)->where('action', 'returned')->first();
        $this->assertNotNull($hist);
        $this->assertEquals('diteruskan', $hist->from_status);
        $this->assertEquals('dalam_penanganan', $hist->to_status);
        $this->assertEquals('Mohon tangani kembali.', $hist->note);
        $this->assertGreaterThanOrEqual(2, ReportHistory::where('report_id', $rep->id)->count());
        // Tidak lagi di daftar diteruskan
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan')->assertDontSee('R-ok');
    }

    public function test_return_note_required(): void
    {
        $rep = $this->createForwardedReport('R-req');
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => ''])
            ->assertSessionHasErrors('note');
        $this->assertEquals('diteruskan', $rep->fresh()->status);
    }

    public function test_invalid_state_rejected(): void
    {
        foreach (['menunggu' => 'W', 'dalam_penanganan' => 'P', 'selesai' => 'S'] as $status => $prefix) {
            $rep = $this->createStatusReport($prefix.'-inv', $status);
            $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertSessionHas('error');
            $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'x'])->assertSessionHas('error');
            $this->assertEquals($status, $rep->fresh()->status);
        }
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/999999')->assertStatus(404);
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/999999/complete', ['note' => 'x'])->assertStatus(404);
    }

    public function test_manipulasi_request_diabaikan(): void
    {
        $rep = $this->createForwardedReport('M-1');
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', [
            'note' => 'ok', 'user_id' => 999, 'status' => 'dalam_penanganan',
            'assigned_teacher_id' => 999, 'description' => 'HACK',
        ])->assertSessionHas('success');
        $fresh = $rep->fresh();
        $this->assertEquals('selesai', $fresh->status);
        $this->assertEquals('Deskripsi asli M-1', $fresh->description);
    }

    public function test_concurrency_single_winner(): void
    {
        $rep = $this->createForwardedReport('Race-1');
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'selesai dulu']);
        $this->assertEquals('selesai', $rep->fresh()->status);
        $this->actingAs($this->kepsek())
            ->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'kembali'])
            ->assertSessionHas('error');
        $this->assertEquals('selesai', $rep->fresh()->status);
        $this->assertEquals(1, ReportHistory::where('report_id', $rep->id)->where('action', 'completed')->count());
        $this->assertEquals(0, ReportHistory::where('report_id', $rep->id)->where('action', 'returned')->count());
    }
}




