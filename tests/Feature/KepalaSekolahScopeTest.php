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

/**
 * Tahap B A1/A2: otorisasi objek kepsek (show + attachment) dan
 * anti-enumerasi guru (403 -> 404 untuk tak-berhak).
 *
 * Aturan kepsek: boleh bila status 'diteruskan', 'dalam_penanganan'
 * (cakupan monitoring), atau pernah 'forwarded' (riwayat diteruskan/
 * selesai/dikembalikan). Selain itu 404.
 */
class KepalaSekolahScopeTest extends TestCase
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
        $tmp = tempnam(sys_get_temp_dir(), 'kss').'.png';
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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_KSS'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_KSS')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_KSS', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_KSS'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswa = $mkUser('kss_siswa', 'Siswa KSS', 'siswa');
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'KSSNIS001', 'class_id' => $class->id]);
        }
        $today = GuruDutyService::todayDay() ?? 'senin';
        foreach ([['kss_guru_a', 'KSSNIP_A'], ['kss_guru_b', 'KSSNIP_B'], ['kss_guru_off', 'KSSNIP_OFF']] as [$u, $nip]) {
            $gu = $mkUser($u, "Guru $u", 'guru');
            $teacher = Teacher::where('user_id', $gu->id)->first()
                ?: Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            if ($u !== 'kss_guru_off' && ! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
                DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
            }
        }
        $mkUser('kss_kepsek', 'Kepsek KSS', 'kepala_sekolah');
        $mkUser('kss_admin', 'Admin KSS', 'admin');

        $this->ids = ['major' => $major->id, 'class' => $class->id, 'cat' => $cat->id, 'siswa' => $siswa->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $sids = Student::where('nis', 'KSSNIS001')->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::where('nis', 'KSSNIS001')->delete();
                $gids = UserBaru::whereIn('username', ['kss_guru_a', 'kss_guru_b', 'kss_guru_off'])->pluck('id');
                $tids = Teacher::whereIn('user_id', $gids)->pluck('id');
                if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->delete();
                Teacher::whereIn('user_id', $gids)->delete();
                UserBaru::whereIn('username', ['kss_siswa', 'kss_guru_a', 'kss_guru_b', 'kss_guru_off', 'kss_kepsek', 'kss_admin'])->delete();
                ReportCategory::whereKey($this->ids['cat'] ?? 0)->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function kepsek(): UserBaru
    {
        return UserBaru::where('username', 'kss_kepsek')->firstOrFail();
    }

    private function guru(string $u): UserBaru
    {
        return UserBaru::where('username', $u)->firstOrFail();
    }

    private function createReport(string $title): Report
    {
        $siswa = UserBaru::findOrFail($this->ids['siswa']);
        $this->actingAs($siswa)->post('/siswa/laporan', [
            'title' => $title, 'category_id' => $this->ids['cat'],
            'location' => 'Kelas - lokasi', 'description' => 'Deskripsi '.$title,
            'photos' => [$this->makePhoto('a.png')],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();

        return Report::where('title', $title)->firstOrFail();
    }

    private function claimForward(string $guruU, Report $rep): void
    {
        $this->actingAs($this->guru($guruU))->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('success');
        $this->actingAs($this->guru($guruU))->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'Butuh kepsek.'])->assertSessionHas('success');
    }

    public function test_kepsek_can_read_forwarded_and_attachment(): void
    {
        $rep = $this->createReport('KSS-FWD');
        $att = $rep->attachments()->firstOrFail();
        $this->claimForward('kss_guru_a', $rep);

        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep->id)->assertStatus(200);
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(200);
    }

    public function test_kepsek_can_read_monitoring_in_progress(): void
    {
        $rep = $this->createReport('KSS-MON');
        $att = $rep->attachments()->firstOrFail();
        $this->actingAs(UserBaru::where('username', 'kss_guru_a')->firstOrFail())
            ->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('success');
        $this->assertEquals('dalam_penanganan', $rep->fresh()->status);

        // Monitoring JSON tetap memuatnya + detail kepsek boleh dibuka.
        $json = $this->actingAs($this->kepsek())->get('/kepala-sekolah/monitoring-dalam-penanganan')->assertStatus(200);
        $this->assertContains($rep->id, collect($json->json('reports'))->pluck('id')->all());
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep->id)->assertStatus(200);
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(200);
    }

    public function test_kepsek_can_read_completed_and_returned_with_history(): void
    {
        // Selesai via kepsek (pernah forwarded).
        $rep = $this->createReport('KSS-DONE');
        $att = $rep->attachments()->firstOrFail();
        $this->claimForward('kss_guru_a', $rep);
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/complete', ['note' => 'Selesai.'])->assertSessionHas('success');
        $this->assertEquals('selesai', $rep->fresh()->status);
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep->id)->assertStatus(200);
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(200);

        // Dikembalikan ke guru (pernah forwarded).
        $rep2 = $this->createReport('KSS-RET');
        $att2 = $rep2->attachments()->firstOrFail();
        $this->claimForward('kss_guru_a', $rep2);
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep2->id.'/return', ['note' => 'Kembali.'])->assertSessionHas('success');
        $this->assertEquals('dalam_penanganan', $rep2->fresh()->status);
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep2->id)->assertStatus(200);
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep2->id.'/attachment/'.$att2->id)->assertStatus(200);
    }

    public function test_kepsek_rejected_for_never_forwarded(): void
    {
        // Menunggu tanpa riwayat forwarded -> 404 detail + attachment.
        $rep = $this->createReport('KSS-WAIT');
        $att = $rep->attachments()->firstOrFail();
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep->id)->assertStatus(404);
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(404);

        // Selesai TANPA riwayat forwarded (langsung oleh guru) -> 404.
        $rep2 = $this->createReport('KSS-DONE-NO-FWD');
        $att2 = $rep2->attachments()->firstOrFail();
        $this->actingAs(UserBaru::where('username', 'kss_guru_a')->firstOrFail())
            ->post('/guru/laporan/'.$rep2->id.'/claim')->assertSessionHas('success');
        $this->actingAs(UserBaru::where('username', 'kss_guru_a')->firstOrFail())
            ->post('/guru/laporan/'.$rep2->id.'/complete', ['note' => 'Selesai langsung.'])->assertSessionHas('success');
        $this->assertEquals('selesai', $rep2->fresh()->status);
        $this->assertFalse(ReportHistory::where('report_id', $rep2->id)->where('action', 'forwarded')->exists());
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep2->id)->assertStatus(404);
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/'.$rep2->id.'/attachment/'.$att2->id)->assertStatus(404);

        // ID tidak ada -> 404 (tak dibedakan dari tak-berhak).
        $this->actingAs($this->kepsek())->get('/kepala-sekolah/laporan/999999')->assertStatus(404);
    }

    public function test_guru_404_for_unauthorized_no_enumeration(): void
    {
        $rep = $this->createReport('KSS-ENUM');
        $att = $rep->attachments()->firstOrFail();
        // Guru off-duty: waiting tak-berhak -> 404 (bukan 403), sama seperti ID tak ada.
        $this->actingAs(UserBaru::where('username', 'kss_guru_off')->firstOrFail())
            ->get('/guru/laporan/'.$rep->id)->assertStatus(404);
        $this->actingAs(UserBaru::where('username', 'kss_guru_off')->firstOrFail())
            ->get('/guru/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(404);
        $this->actingAs(UserBaru::where('username', 'kss_guru_off')->firstOrFail())
            ->get('/guru/laporan/999999')->assertStatus(404);

        // Akses sah tetap 200: guru piket boleh lihat waiting + attachment.
        $guruA = UserBaru::where('username', 'kss_guru_a')->firstOrFail();
        $this->actingAs($guruA)->get('/guru/laporan/'.$rep->id)->assertStatus(200);
        $this->actingAs($guruA)->get('/guru/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(200);

        // Setelah claim A, guru B (piket tapi bukan pemilik) -> 404 konsisten.
        $this->actingAs($guruA)->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('success');
        $guruB = UserBaru::where('username', 'kss_guru_b')->firstOrFail();
        $this->actingAs($guruB)->get('/guru/laporan/'.$rep->id)->assertStatus(404);
        $this->actingAs($guruB)->get('/guru/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(404);
    }

    public function test_siswa_admin_no_new_access(): void
    {
        $rep = $this->createReport('KSS-NO-NEW');
        $siswa = UserBaru::findOrFail($this->ids['siswa']);
        $admin = UserBaru::where('username', 'kss_admin')->firstOrFail();
        // Siswa tetap hanya miliknya; admin tetap 403 di area kepsek (role).
        $this->actingAs($siswa)->get('/kepala-sekolah/laporan/'.$rep->id)->assertStatus(403);
        $this->actingAs($admin)->get('/kepala-sekolah/laporan/'.$rep->id)->assertStatus(403);
        $this->actingAs($siswa)->get('/guru/laporan/'.$rep->id)->assertStatus(403);
        $this->actingAs($admin)->get('/guru/laporan/'.$rep->id)->assertStatus(403);
    }

    public function test_forward_complete_return_flow_no_regression(): void
    {
        $rep = $this->createReport('KSS-FLOW');
        $this->claimForward('kss_guru_a', $rep);
        $this->assertEquals('diteruskan', $rep->fresh()->status);
        $this->actingAs($this->kepsek())->post('/kepala-sekolah/laporan/'.$rep->id.'/return', ['note' => 'Kembali.'])->assertSessionHas('success');
        $this->assertEquals('dalam_penanganan', $rep->fresh()->status);
        // Guru pemilik menyelesaikan setelah return.
        $this->actingAs(UserBaru::where('username', 'kss_guru_a')->firstOrFail())
            ->post('/guru/laporan/'.$rep->id.'/complete', ['note' => 'Selesai akhir.'])->assertSessionHas('success');
        $this->assertEquals('selesai', $rep->fresh()->status);
        $actions = ReportHistory::where('report_id', $rep->id)->orderBy('id')->pluck('action')->all();
        $this->assertContains('forwarded', $actions);
        $this->assertContains('returned', $actions);
        $this->assertContains('completed', $actions);
    }
}
