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

class GuruHandleStep2Test extends TestCase
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
        $tmp = tempnam(sys_get_temp_dir(), 'f7s2').'.png';
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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F7S2'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F7S2')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F7S2', 'status' => 'aktif']);
        ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F7S2'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $siswaUser = UserBaru::where('username', 'f7s2_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa F7S2', 'username' => 'f7s2_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        if (! Student::where('user_id', $siswaUser->id)->exists()) {
            Student::create(['user_id' => $siswaUser->id, 'nis' => 'F7S2NIS001', 'class_id' => $class->id]);
        }

        $today = GuruDutyService::todayDay() ?? 'senin';
        foreach ([['f7s2_guru_a', 'F7S2NIP_A'], ['f7s2_guru_b', 'F7S2NIP_B']] as [$u, $nip]) {
            $gu = UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => "Guru $u", 'username' => $u, 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
            $teacher = Teacher::where('user_id', $gu->id)->first()
                ?: Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
                DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            $sids = Student::where('nis', 'F7S2NIS001')->pluck('id');
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

    private function createOwnedReport(string $guruUsername, string $title): Report
    {
        $st = Student::where('nis', 'F7S2NIS001')->firstOrFail();
        $cat = ReportCategory::where('name', 'TEST_Kat_F7S2')->firstOrFail();
        $gu = UserBaru::where('username', $guruUsername)->firstOrFail();
        $teacher = Teacher::where('user_id', $gu->id)->firstOrFail();
        $report = Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'Deskripsi asli '.$title,
            'location' => 'Kelas - lokasi', 'priority' => $cat->priority,
            'status' => 'dalam_penanganan', 'assigned_teacher_id' => $teacher->id,
            'assigned_at' => now(),
        ]);
        $photo = $this->makePhoto('a.png');
        $path = $photo->store('reports', 'local');
        $report->attachments()->create([
            'file_patch' => $path, 'original_name' => 'a.png',
            'file_size' => $photo->getSize(), 'mime_type' => 'image/png',
        ]);

        return $report;
    }

    private function createWaitingReport(string $title): Report
    {
        $st = Student::where('nis', 'F7S2NIS001')->firstOrFail();
        $cat = ReportCategory::where('name', 'TEST_Kat_F7S2')->firstOrFail();

        return Report::create([
            'student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $cat->id,
            'title' => $title, 'description' => 'Deskripsi '.$title,
            'location' => 'Kelas - lokasi', 'priority' => $cat->priority, 'status' => 'menunggu',
        ]);
    }

    private function guru(string $username): UserBaru
    {
        return UserBaru::where('username', $username)->firstOrFail();
    }

    public function test_complete_success(): void
    {
        $rep = $this->createOwnedReport('f7s2_guru_a', 'C-ok');
        $before = ['desc' => $rep->description, 'cat' => $rep->category_id, 'prio' => $rep->priority, 'st' => $rep->student_id, 'cl' => $rep->class_id];
        $this->actingAs($this->guru('f7s2_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'Sudah diperbaiki.'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Laporan berhasil diselesaikan.');
        $fresh = $rep->fresh();
        $this->assertEquals('selesai', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertEquals($before['desc'], $fresh->description);
        $this->assertEquals($before['cat'], (int) $fresh->category_id);
        $this->assertEquals($before['prio'], $fresh->priority);
        $this->assertEquals($before['st'], (int) $fresh->student_id);
        $this->assertEquals($before['cl'], (int) $fresh->class_id);
        $hist = ReportHistory::where('report_id', $rep->id)->where('action', 'completed')->first();
        $this->assertNotNull($hist);
        $this->assertEquals('dalam_penanganan', $hist->from_status);
        $this->assertEquals('selesai', $hist->to_status);
        $this->assertEquals('Sudah diperbaiki.', $hist->note);
    }

    public function test_forward_success_note_required(): void
    {
        $rep = $this->createOwnedReport('f7s2_guru_a', 'F-ok');
        $this->actingAs($this->guru('f7s2_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/forward', ['note' => ''])
            ->assertSessionHasErrors('note');
        $this->assertEquals('dalam_penanganan', $rep->fresh()->status);

        $this->actingAs($this->guru('f7s2_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'Perlu persetujuan pengadaan.'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Laporan berhasil diteruskan ke Kepala Sekolah.');
        $fresh = $rep->fresh();
        $this->assertEquals('diteruskan', $fresh->status);
        $this->assertEquals('Deskripsi asli F-ok', $fresh->description);
        $hist = ReportHistory::where('report_id', $rep->id)->where('action', 'forwarded')->first();
        $this->assertNotNull($hist);
        $this->assertEquals('Perlu persetujuan pengadaan.', $hist->note);
        $this->assertEquals('dalam_penanganan', $hist->from_status);
        $this->assertEquals('diteruskan', $hist->to_status);
    }

    public function test_ownership_other_guru_rejected(): void
    {
        $rep = $this->createOwnedReport('f7s2_guru_a', 'Milik-A');
        $this->actingAs($this->guru('f7s2_guru_b'))
            ->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'hack'])
            ->assertSessionHas('error');
        $this->actingAs($this->guru('f7s2_guru_b'))
            ->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'hack'])
            ->assertSessionHas('error');
        $this->assertEquals('dalam_penanganan', $rep->fresh()->status);
        $this->actingAs($this->guru('f7s2_guru_b'))->get('/guru/laporan/'.$rep->id)->assertStatus(403);
    }

    public function test_status_protection(): void
    {
        $waiting = $this->createWaitingReport('W-protect');
        $this->actingAs($this->guru('f7s2_guru_a'))
            ->post('/guru/laporan/'.$waiting->id.'/complete', ['note' => 'x'])
            ->assertSessionHas('error');
        $this->actingAs($this->guru('f7s2_guru_a'))
            ->post('/guru/laporan/'.$waiting->id.'/forward', ['note' => 'x'])
            ->assertSessionHas('error');
        $this->assertEquals('menunggu', $waiting->fresh()->status);

        $rep = $this->createOwnedReport('f7s2_guru_a', 'Final');
        $this->actingAs($this->guru('f7s2_guru_a'))->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'done']);
        $this->actingAs($this->guru('f7s2_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'lagi'])
            ->assertSessionHas('error');
        $this->actingAs($this->guru('f7s2_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'lagi'])
            ->assertSessionHas('error');
        $this->assertEquals('selesai', $rep->fresh()->status);
        $this->assertEquals(1, ReportHistory::where('report_id', $rep->id)->where('action', 'completed')->count());
    }

    public function test_manipulasi_status_diabaikan(): void
    {
        $rep = $this->createOwnedReport('f7s2_guru_a', 'Manip');
        $this->actingAs($this->guru('f7s2_guru_a'))->post('/guru/laporan/'.$rep->id.'/complete', [
            'note' => 'ok', 'status' => 'diteruskan', 'assigned_teacher_id' => 999,
            'student_id' => 999, 'description' => 'HACK',
        ])->assertSessionHas('success');
        $fresh = $rep->fresh();
        $this->assertEquals('selesai', $fresh->status);
        $this->assertEquals('Deskripsi asli Manip', $fresh->description);
    }

    public function test_roles_guest_rejected(): void
    {
        $rep = $this->createOwnedReport('f7s2_guru_a', 'Sec');
        $siswa = $this->guru('f7s2_siswa') ?? UserBaru::where('username', 'f7s2_siswa')->first();
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $kepsek = UserBaru::where('role', 'kepala_sekolah')->where('status', 'aktif')->first();
        if ($siswa) $this->actingAs($siswa)->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
        if ($admin) $this->actingAs($admin)->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertStatus(403);
        if ($kepsek) $this->actingAs($kepsek)->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'x'])->assertStatus(403);
        $this->refreshApplication();
        Storage::fake('local');
        $this->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'x'])->assertRedirect('/login');
    }
}




