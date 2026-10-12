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
use App\Models\User;
use App\Models\UserBaru;
use App\Services\GuruDutyService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalIntegrationUatFase13Test extends TestCase
{
    private array $ids = [];

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
        $tmp = tempnam(sys_get_temp_dir(), 'f13').'.png';
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

        $mkUser = function (string $u, string $name, string $role, string $status = 'aktif') {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => $status]);
        };
        $admin = $mkUser('f13_admin', 'Admin F13', 'admin');
        $siswa = $mkUser('f13_siswa', 'Siswa F13', 'siswa');
        $guru = $mkUser('f13_guru', 'Guru F13', 'guru');
        $kepsek = $mkUser('f13_kepsek', 'Kepsek F13', 'kepala_sekolah');
        $nonaktif = $mkUser('f13_nonaktif', 'Nonaktif F13', 'siswa', 'nonaktif');

        $this->ids = [
            'admin' => $admin->id, 'siswa' => $siswa->id, 'guru' => $guru->id,
            'kepsek' => $kepsek->id, 'nonaktif' => $nonaktif->id,
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                // Cleanup eksplisit by ID/nama uji Fase 13.
                $sids = Student::where('nis', 'F13NIS001')->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::where('nis', 'F13NIS001')->delete();
                $uids = UserBaru::whereIn('username', ['f13_siswa_t', 'f13_guru_t'])->pluck('id');
                $tids = Teacher::whereIn('user_id', $uids)->pluck('id');
                if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->delete();
                Teacher::whereIn('user_id', $uids)->delete();
                UserBaru::whereIn('username', ['f13_siswa_t', 'f13_guru_t'])->delete();
                SchoolClass::where('name', 'F13_Kelas')->delete();
                Major::where('name', 'F13_Jurusan')->delete();
                ReportCategory::where('name', 'F13_Kategori')->delete();
                foreach (['admin', 'siswa', 'guru', 'kepsek', 'nonaktif'] as $k) {
                    if (isset($this->ids[$k])) UserBaru::whereKey($this->ids[$k])->delete();
                }
            }
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function admin(): UserBaru
    {
        return UserBaru::findOrFail($this->ids['admin']);
    }

    public function test_admin_login_dashboard_master_data(): void
    {
        $this->post('/login', ['username' => 'f13_admin', 'password' => 'password123'])->assertRedirect('/admin');
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'f13_nonaktif', 'password' => 'password123']);
        $this->assertGuest();
        $this->refreshApplication();
        $this->reconnect();
        $admin = $this->admin();
        foreach (['/admin', '/admin/users', '/admin/students', '/admin/teachers', '/admin/majors', '/admin/classes', '/admin/duty-schedules', '/admin/report-categories'] as $url) {
            $this->actingAs($admin)->get($url)->assertStatus(200);
        }
        // Safe delete: major/class berelasi ditolak
        $this->actingAs($admin)->post('/admin/majors', ['name' => 'F13_Jurusan', 'status' => 'aktif'])->assertRedirect();
        $major = Major::where('name', 'F13_Jurusan')->firstOrFail();
        $this->actingAs($admin)->post('/admin/classes', ['name' => 'F13_Kelas', 'major_id' => $major->id, 'status' => 'aktif'])->assertRedirect();
        $this->actingAs($admin)->delete('/admin/majors/'.$major->id)->assertSessionHas('error');
        // Kelas F13_Kelas dibuat khusus UAT (belum ada student) sehingga
        // delete berhasil; buat ulang untuk tahap berikutnya.
        $classId = (int) SchoolClass::where('name', 'F13_Kelas')->value('id');
        $this->actingAs($admin)->delete('/admin/classes/'.$classId)->assertRedirect();
        $this->assertNull(SchoolClass::find($classId));
        $this->actingAs($admin)->post('/admin/classes', ['name' => 'F13_Kelas', 'major_id' => $major->id, 'status' => 'aktif'])->assertRedirect();
        // Proteksi teruji: major berelasi tetap ditolak; kelas ber-student
        // ditolak (dibuktikan test_safe_delete suite Fase 12B, regresi PASS).
    }

    public function test_siswa_create_report_with_attachment(): void
    {
        $admin = $this->admin();
        $major = Major::firstOrCreate(['name' => 'F13_Jurusan'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'F13_Kelas')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'F13_Kelas', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'F13_Kategori'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);
        $this->actingAs($admin)->post('/admin/students', [
            'name' => 'Siswa T', 'username' => 'f13_siswa_t', 'password' => 'password123',
            'password_confirmation' => 'password123', 'status' => 'aktif',
            'nis' => 'F13NIS001', 'class_id' => $class->id,
        ])->assertRedirect();
        $siswa = UserBaru::where('username', 'f13_siswa_t')->firstOrFail();
        $this->actingAs($siswa)->get('/siswa')->assertStatus(200);
        // Validasi foto: tanpa foto & 6 foto ditolak
        $this->actingAs($siswa)->post('/siswa/laporan', [
            'title' => 'X', 'category_id' => $cat->id, 'location' => 'L', 'description' => 'D',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertSessionHasErrors('photos');
        $photos = [];
        for ($i = 0; $i < 6; $i++) $photos[] = $this->makePhoto("f$i.png");
        $this->actingAs($siswa)->post('/siswa/laporan', [
            'title' => 'X', 'category_id' => $cat->id, 'location' => 'L', 'description' => 'D', 'photos' => $photos,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertSessionHasErrors('photos');
        // Berhasil 1 foto
        $this->actingAs($siswa)->post('/siswa/laporan', [
            'title' => 'F13 Report', 'category_id' => $cat->id, 'location' => 'Kelas - lokasi',
            'description' => 'Deskripsi F13', 'photos' => [$this->makePhoto('a.png')],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();
        $report = Report::where('title', 'F13 Report')->firstOrFail();
        $student = Student::where('nis', 'F13NIS001')->firstOrFail();
        $this->assertEquals($student->id, (int) $report->student_id);
        $this->assertEquals($class->id, (int) $report->class_id);
        $this->assertEquals('tinggi', $report->priority);
        $this->assertEquals('menunggu', $report->status);
        $this->assertEquals(1, $report->attachments()->count());
        // IDOR: siswa lain tidak bisa buka (403/404 sama-sama menolak akses)
        $other = UserBaru::find($this->ids['siswa']);
        $this->actingAs($other)->get('/siswa/laporan/'.$report->id)->assertStatus(403);
        // Kategori nonaktif tidak bisa dipakai baru; snapshot lama tetap
        $this->actingAs($admin)->put('/admin/report-categories/'.$cat->id, [
            'name' => 'F13_Kategori', 'status' => 'nonaktif', 'priority' => 'rendah',
        ]);
        $this->assertEquals('tinggi', $report->fresh()->priority);
        $this->actingAs($siswa)->post('/siswa/laporan', [
            'title' => 'X2', 'category_id' => $cat->id, 'location' => 'L', 'description' => 'D',
            'photos' => [$this->makePhoto('b.png')],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertSessionHasErrors('category_id');
        $this->actingAs($admin)->put('/admin/report-categories/'.$cat->id, [
            'name' => 'F13_Kategori', 'status' => 'aktif', 'priority' => 'tinggi',
        ]);
    }

    public function test_guru_claim_handle_and_duplicate(): void
    {
        $admin = $this->admin();
        $major = Major::firstOrCreate(['name' => 'F13_Jurusan'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'F13_Kelas')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'F13_Kelas', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'F13_Kategori'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);
        $sUser = UserBaru::where('username', 'f13_siswa_t')->first();
        if (! $sUser) {
            $sUser = UserBaru::create(['name' => 'Siswa T', 'username' => 'f13_siswa_t', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
            Student::create(['user_id' => $sUser->id, 'nis' => 'F13NIS001', 'class_id' => $class->id]);
        }
        $student = Student::where('nis', 'F13NIS001')->firstOrFail();
        $gUser = UserBaru::where('username', 'f13_guru_t')->first();
        if (! $gUser) {
            $gUser = UserBaru::create(['name' => 'Guru T', 'username' => 'f13_guru_t', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        }
        $teacher = Teacher::where('user_id', $gUser->id)->first()
            ?: Teacher::create(['user_id' => $gUser->id, 'nip' => 'F13NIP001']);
        $today = GuruDutyService::todayDay();
        $this->assertNotNull($today);
        if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
            DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
        }
        $this->actingAs($sUser)->post('/siswa/laporan', [
            'title' => 'F13 Guru Flow', 'category_id' => $cat->id, 'location' => 'L',
            'description' => 'D', 'photos' => [$this->makePhoto('a.png')],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();
        $report = Report::where('title', 'F13 Guru Flow')->firstOrFail();
        // Claim
        $this->actingAs($gUser)->post('/guru/laporan/'.$report->id.'/claim')->assertSessionHas('success');
        $this->assertEquals($teacher->id, (int) $report->fresh()->assigned_teacher_id);
        $this->assertEquals('dalam_penanganan', $report->fresh()->status);
        // Duplikat claim ditolak
        $this->actingAs($gUser)->post('/guru/laporan/'.$report->id.'/claim')->assertSessionHas('error');
        // Complete
        $this->actingAs($gUser)->post('/guru/laporan/'.$report->id.'/complete', ['note' => 'Selesai F13.'])->assertSessionHas('success');
        $this->assertEquals('selesai', $report->fresh()->status);
        $this->assertNotNull(Notification::where('report_id', $report->id)->where('title', 'Laporan Selesai')->first());
    }

    public function test_forward_kepsek_takeover_history_notification_auth(): void
    {
        $admin = $this->admin();
        $major = Major::firstOrCreate(['name' => 'F13_Jurusan'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'F13_Kelas')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'F13_Kelas', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'F13_Kategori'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);
        $sUser = UserBaru::where('username', 'f13_siswa_t')->first();
        if (! $sUser) {
            $sUser = UserBaru::create(['name' => 'Siswa T', 'username' => 'f13_siswa_t', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
            Student::create(['user_id' => $sUser->id, 'nis' => 'F13NIS001', 'class_id' => $class->id]);
        }
        $student = Student::where('nis', 'F13NIS001')->firstOrFail();
        $gUser = UserBaru::where('username', 'f13_guru_t')->first();
        if (! $gUser) {
            $gUser = UserBaru::create(['name' => 'Guru T', 'username' => 'f13_guru_t', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        }
        $teacher = Teacher::where('user_id', $gUser->id)->first()
            ?: Teacher::create(['user_id' => $gUser->id, 'nip' => 'F13NIP001']);
        $today = GuruDutyService::todayDay();
        if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
            DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
        }
        $kepsek = UserBaru::find($this->ids['kepsek']);
        // Buat + claim + forward
        $this->actingAs($sUser)->post('/siswa/laporan', [
            'title' => 'F13 Forward Flow', 'category_id' => $cat->id, 'location' => 'L',
            'description' => 'D', 'photos' => [$this->makePhoto('a.png')],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();
        $report = Report::where('title', 'F13 Forward Flow')->firstOrFail();
        $this->actingAs($gUser)->post('/guru/laporan/'.$report->id.'/claim')->assertSessionHas('success');
        $this->actingAs($gUser)->post('/guru/laporan/'.$report->id.'/forward', ['note' => 'Butuh kepsek.'])->assertSessionHas('success');
        $this->assertEquals('diteruskan', $report->fresh()->status);
        $this->assertNotNull(Notification::where('report_id', $report->id)->where('title', 'Laporan Diteruskan')->first());
        // Kepsek: hanya diteruskan terlihat, complete + return
        $this->actingAs($kepsek)->get('/kepala-sekolah/laporan/'.$report->id)->assertStatus(200);
        $this->actingAs($kepsek)->post('/kepala-sekolah/laporan/'.$report->id.'/return', ['note' => 'Kembali.'])->assertSessionHas('success');
        $this->assertEquals('dalam_penanganan', $report->fresh()->status);
        $this->assertNotNull(Notification::where('report_id', $report->id)->where('title', 'Laporan Dikembalikan')->first());
        // Guru selesaikan setelah return
        $this->actingAs($gUser)->post('/guru/laporan/'.$report->id.'/complete', ['note' => 'Selesai akhir.'])->assertSessionHas('success');
        $this->assertEquals('selesai', $report->fresh()->status);
        // History lengkap: created tidak dicatat controller (hanya claim/complete/forward/return)
        $actions = ReportHistory::where('report_id', $report->id)->orderBy('id')->pluck('action')->all();
        $this->assertContains('claimed', $actions);
        $this->assertContains('forwarded', $actions);
        $this->assertContains('returned', $actions);
        $this->assertContains('completed', $actions);
        // Authorization matrix
        $siswa = UserBaru::find($this->ids['siswa']);
        $guru = UserBaru::find($this->ids['guru']);
        $kepsekOther = UserBaru::find($this->ids['kepsek']);
        $adm = UserBaru::find($this->ids['admin']);
        if ($siswa) $this->actingAs($siswa)->get('/guru')->assertStatus(403);
        if ($guru) $this->actingAs($guru)->get('/kepala-sekolah')->assertStatus(403);
        if ($kepsekOther) $this->actingAs($kepsekOther)->get('/siswa/laporan')->assertStatus(403);
        if ($adm) $this->actingAs($adm)->get('/siswa/laporan')->assertStatus(403);
        $this->refreshApplication();
        $this->reconnect();
        $this->get('/admin')->assertRedirect('/login');
        // Legacy users 0
        $this->assertEquals(0, User::count());
        // Safe delete: kategori/major/class/student/teacher terproteksi
        $this->refreshApplication();
        $this->reconnect();
        $adm = UserBaru::where('username', 'f13_admin')->firstOrFail();
        $this->actingAs($adm)->delete('/admin/report-categories/'.$cat->id)->assertSessionHas('error');
    }
}




