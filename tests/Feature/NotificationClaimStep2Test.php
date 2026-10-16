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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationClaimStep2Test extends TestCase
{
    private array $ids = [];

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
        $tmp = tempnam(sys_get_temp_dir(), 'f9s2').'.png';
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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F9S2'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F9S2')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F9S2', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F9S2'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswaA = $mkUser('f9s2_siswa_a', 'Siswa A F9S2', 'siswa');
        $siswaB = $mkUser('f9s2_siswa_b', 'Siswa B F9S2', 'siswa');
        if (! Student::where('user_id', $siswaA->id)->exists()) {
            Student::create(['user_id' => $siswaA->id, 'nis' => 'F9S2NIS_A', 'class_id' => $class->id]);
        }
        if (! Student::where('user_id', $siswaB->id)->exists()) {
            Student::create(['user_id' => $siswaB->id, 'nis' => 'F9S2NIS_B', 'class_id' => $class->id]);
        }

        $today = GuruDutyService::todayDay() ?? 'senin';
        foreach ([['f9s2_guru_a', 'F9S2NIP_A'], ['f9s2_guru_b', 'F9S2NIP_B']] as [$u, $nip]) {
            $gu = $mkUser($u, "Guru $u", 'guru');
            $teacher = Teacher::where('user_id', $gu->id)->first()
                ?: Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
                DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
            }
        }
        $guC = $mkUser('f9s2_guru_c', 'Guru C F9S2', 'guru');
        if (! Teacher::where('user_id', $guC->id)->exists()) {
            Teacher::create(['user_id' => $guC->id, 'nip' => 'F9S2NIP_C']);
        }

        $this->ids = [
            'major' => $major->id, 'class' => $class->id, 'cat' => $cat->id,
            'siswaA' => $siswaA->id, 'siswaB' => UserBaru::where('username', 'f9s2_siswa_b')->value('id'),
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            // Hapus eksplisit by ID — tanpa pola LIKE luas.
            if (! empty($this->ids)) {
                $sids = Student::whereIn('user_id', [$this->ids['siswaA'] ?? 0, $this->ids['siswaB'] ?? 0])->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::whereIn('user_id', [$this->ids['siswaA'] ?? 0, $this->ids['siswaB'] ?? 0])->delete();
                $guruIds = UserBaru::whereIn('username', ['f9s2_guru_a', 'f9s2_guru_b', 'f9s2_guru_c'])->pluck('id');
                $teacherIds = Teacher::whereIn('user_id', $guruIds)->pluck('id');
                if ($teacherIds->isNotEmpty()) DutySchedule::whereIn('teacher_id', $teacherIds)->delete();
                Teacher::whereIn('user_id', $guruIds)->delete();
                UserBaru::whereIn('id', [$this->ids['siswaA'] ?? 0, $this->ids['siswaB'] ?? 0])->delete();
                UserBaru::whereIn('username', ['f9s2_guru_a', 'f9s2_guru_b', 'f9s2_guru_c'])->delete();
                if (isset($this->ids['cat'])) ReportCategory::whereKey($this->ids['cat'])->delete();
                if (isset($this->ids['class'])) SchoolClass::whereKey($this->ids['class'])->delete();
                if (isset($this->ids['major'])) Major::whereKey($this->ids['major'])->delete();
            }
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function postReport(string $username, string $title): Report
    {
        $user = UserBaru::where('username', $username)->firstOrFail();
        $cat = ReportCategory::findOrFail($this->ids['cat']);
        $this->actingAs($user)->post('/siswa/laporan', [
            'title' => $title, 'category_id' => $cat->id,
            'location' => 'Kelas - lokasi', 'description' => 'Deskripsi '.$title,
            'photos' => [$this->makePhoto('a.png')],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();

        return Report::where('title', $title)->firstOrFail();
    }

    private function guru(string $username): UserBaru
    {
        return UserBaru::where('username', $username)->firstOrFail();
    }

    public function test_claim_creates_student_notification(): void
    {
        $rep = $this->postReport('f9s2_siswa_a', 'C-notif');
        $studentUserId = (int) UserBaru::where('username', 'f9s2_siswa_a')->value('id');
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Sedang Ditangani')->count());

        $this->actingAs($this->guru('f9s2_guru_a'))
            ->post('/guru/laporan/'.$rep->id.'/claim')
            ->assertSessionHas('success');

        $fresh = $rep->fresh();
        $this->assertEquals('dalam_penanganan', $fresh->status);
        $this->assertNotNull($fresh->assigned_at);
        $this->assertNotNull(ReportHistory::where('report_id', $rep->id)->where('action', 'claimed')->first());

        $notif = Notification::where('report_id', $rep->id)->where('title', 'Laporan Sedang Ditangani')->first();
        $this->assertNotNull($notif);
        $this->assertEquals($studentUserId, (int) $notif->user_id);
        $this->assertEquals('Laporan Anda sedang ditangani oleh Guru Piket.', $notif->message);
        $this->assertFalse((bool) $notif->is_read);
        // Data siswa tidak berubah
        $this->assertEquals('Deskripsi C-notif', $fresh->description);
        // Role lain tidak dapat notifikasi event claim
        $this->assertEquals(1, Notification::where('report_id', $rep->id)->where('title', 'Laporan Sedang Ditangani')->count());
    }

    public function test_failed_claim_creates_no_notification(): void
    {
        $rep = $this->postReport('f9s2_siswa_a', 'C-fail');
        $this->actingAs($this->guru('f9s2_guru_a'))->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('success');
        // Guru B claim laporan yang sama -> gagal, tanpa notifikasi baru
        $this->actingAs($this->guru('f9s2_guru_b'))->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('error');
        $this->assertEquals(1, Notification::where('report_id', $rep->id)->where('title', 'Laporan Sedang Ditangani')->count());
        // Claim ulang A -> ditolak, tanpa notifikasi baru
        $this->actingAs($this->guru('f9s2_guru_a'))->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('error');
        $this->assertEquals(1, Notification::where('report_id', $rep->id)->where('title', 'Laporan Sedang Ditangani')->count());
    }

    public function test_only_owner_gets_notification(): void
    {
        $rep = $this->postReport('f9s2_siswa_a', 'C-owner');
        $this->actingAs($this->guru('f9s2_guru_a'))->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('success');
        $ownerId = (int) UserBaru::where('username', 'f9s2_siswa_a')->value('id');
        $otherId = (int) UserBaru::where('username', 'f9s2_siswa_b')->value('id');
        $this->assertEquals(1, Notification::where('report_id', $rep->id)->where('user_id', $ownerId)->where('title', 'Laporan Sedang Ditangani')->count());
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('user_id', $otherId)->count());
    }

    public function test_security_roles_and_guest(): void
    {
        $rep = $this->postReport('f9s2_siswa_a', 'C-sec');
        $siswa = $this->guru('f9s2_siswa_a');
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $kepsek = UserBaru::where('role', 'kepala_sekolah')->where('status', 'aktif')->first();
        $this->actingAs($siswa)->post('/guru/laporan/'.$rep->id.'/claim')->assertStatus(403);
        if ($admin) $this->actingAs($admin)->post('/guru/laporan/'.$rep->id.'/claim')->assertStatus(403);
        if ($kepsek) $this->actingAs($kepsek)->post('/guru/laporan/'.$rep->id.'/claim')->assertStatus(403);
        $this->refreshApplication();
        $this->reconnect();
        Storage::fake('local');
        $this->post('/guru/laporan/'.$rep->id.'/claim')->assertRedirect('/login');
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Sedang Ditangani')->count());
    }

    public function test_offduty_guru_claim_rejected_no_notification(): void
    {
        $rep = $this->postReport('f9s2_siswa_a', 'C-off');
        $this->actingAs($this->guru('f9s2_guru_c'))->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('error');
        $this->assertEquals('menunggu', $rep->fresh()->status);
        $this->assertEquals(0, Notification::where('report_id', $rep->id)->where('title', 'Laporan Sedang Ditangani')->count());
    }

    public function test_student_dashboard_shows_claim_notification(): void
    {
        $rep = $this->postReport('f9s2_siswa_a', 'C-ui');
        $this->actingAs($this->guru('f9s2_guru_a'))->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('success');
        $siswa = UserBaru::where('username', 'f9s2_siswa_a')->firstOrFail();
        $this->actingAs($siswa)->get('/siswa')->assertStatus(200)->assertSee('Laporan Sedang Ditangani');
        // IDOR tetap: siswa lain tidak bisa buka report ini
        $other = UserBaru::where('username', 'f9s2_siswa_b')->firstOrFail();
        $this->actingAs($other)->get('/siswa/laporan/'.$rep->id)->assertStatus(404);
    }
}




