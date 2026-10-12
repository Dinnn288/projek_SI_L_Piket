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

class AttachmentSecurityFase142Test extends TestCase
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
        $tmp = tempnam(sys_get_temp_dir(), 'f142').'.png';
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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F142'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F142')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F142', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F142'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswaA = $mkUser('f142_siswa_a', 'Siswa A F142', 'siswa');
        $siswaB = $mkUser('f142_siswa_b', 'Siswa B F142', 'siswa');
        if (! Student::where('user_id', $siswaA->id)->exists()) {
            Student::create(['user_id' => $siswaA->id, 'nis' => 'F142NIS_A', 'class_id' => $class->id]);
        }
        if (! Student::where('user_id', $siswaB->id)->exists()) {
            Student::create(['user_id' => $siswaB->id, 'nis' => 'F142NIS_B', 'class_id' => $class->id]);
        }
        $today = GuruDutyService::todayDay() ?? 'senin';
        foreach ([['f142_guru_a', 'F142NIP_A'], ['f142_guru_b', 'F142NIP_B']] as [$u, $nip]) {
            $gu = $mkUser($u, "Guru $u", 'guru');
            $teacher = Teacher::where('user_id', $gu->id)->first()
                ?: Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
                DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
            }
        }
        $mkUser('f142_kepsek', 'Kepsek F142', 'kepala_sekolah');

        $this->ids = [
            'major' => $major->id, 'class' => $class->id, 'cat' => $cat->id,
            'siswaA' => $siswaA->id,
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $sids = Student::whereIn('nis', ['F142NIS_A', 'F142NIS_B'])->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::whereIn('nis', ['F142NIS_A', 'F142NIS_B'])->delete();
                $gids = UserBaru::whereIn('username', ['f142_guru_a', 'f142_guru_b'])->pluck('id');
                $tids = Teacher::whereIn('user_id', $gids)->pluck('id');
                if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->delete();
                Teacher::whereIn('user_id', $gids)->delete();
                UserBaru::whereIn('username', ['f142_siswa_a', 'f142_siswa_b', 'f142_guru_a', 'f142_guru_b', 'f142_kepsek'])->delete();
                ReportCategory::whereKey($this->ids['cat'] ?? 0)->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function createReport(string $title): Report
    {
        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        $this->actingAs($siswaA)->post('/siswa/laporan', [
            'title' => $title, 'category_id' => $this->ids['cat'],
            'location' => 'Kelas - lokasi', 'description' => 'Deskripsi '.$title,
            'photos' => [$this->makePhoto('a.png')],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();

        return Report::where('title', $title)->firstOrFail();
    }

    public function test_student_own_and_other_and_guest(): void
    {
        $rep = $this->createReport('F142-A');
        $att = $rep->attachments()->firstOrFail();
        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        $siswaB = UserBaru::where('username', 'f142_siswa_b')->firstOrFail();
        // Pemilik: 200 + konten gambar
        $ok = $this->actingAs($siswaA)->get('/siswa/laporan/'.$rep->id.'/attachment/'.$att->id);
        $ok->assertStatus(200);
        $this->assertStringStartsWith('image/', $ok->headers->get('Content-Type'));
        // Siswa lain: ditolak
        $this->actingAs($siswaB)->get('/siswa/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(404);
        // Guest: redirect login
        $this->refreshApplication();
        $this->reconnect();
        $this->get('/siswa/laporan/'.$rep->id.'/attachment/'.$att->id)->assertRedirect('/login');
        // File tersimpan di disk private, bukan public
        $this->assertFalse(Storage::disk('public')->exists($att->fresh()->file_patch));
    }

    public function test_idor_mismatch_and_traversal(): void
    {
        $repA = $this->createReport('F142-M1');
        $repB = $this->createReport('F142-M2');
        $attA = $repA->attachments()->firstOrFail();
        $attB = $repB->attachments()->firstOrFail();
        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        $this->actingAs($siswaA)->get('/siswa/laporan/'.$repA->id.'/attachment/'.$attB->id)->assertStatus(404);
        $this->actingAs($siswaA)->get('/siswa/laporan/'.$repB->id.'/attachment/'.$attA->id)->assertStatus(404);
        // Path traversal: attachment ID non-numerik / tidak ada -> 404, bukan file lain
        $this->actingAs($siswaA)->get('/siswa/laporan/'.$repA->id.'/attachment/999999')->assertStatus(404);
    }

    public function test_guru_authorized_and_unauthorized(): void
    {
        $rep = $this->createReport('F142-G');
        $att = $rep->attachments()->firstOrFail();
        $guruA = UserBaru::where('username', 'f142_guru_a')->firstOrFail();
        $guruB = UserBaru::where('username', 'f142_guru_b')->firstOrFail();
        // Guru piket boleh (waiting, sedang piket)
        $this->actingAs($guruA)->get('/guru/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(200);
        // Guru B juga piket hari ini -> boleh (aturan waiting multi-guru)
        $this->actingAs($guruB)->get('/guru/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(200);
        // Setelah Guru A claim, Guru B ditolak -> 404 (A2, samarkan eksistensi)
        $this->actingAs($guruA)->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('success');
        $this->actingAs($guruB)->get('/guru/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(404);
        // Pemilik (Guru A) tetap boleh
        $this->actingAs($guruA)->get('/guru/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(200);
    }

    public function test_kepsek_authorized_and_unauthorized(): void
    {
        $rep = $this->createReport('F142-K');
        $att = $rep->attachments()->firstOrFail();
        $kepsek = UserBaru::where('username', 'f142_kepsek')->firstOrFail();
        $guruA = UserBaru::where('username', 'f142_guru_a')->firstOrFail();
        // A1: laporan menunggu tanpa riwayat forwarded -> kepsek 404.
        $this->actingAs($kepsek)->get('/kepala-sekolah/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(404);
        $this->actingAs($kepsek)->get('/kepala-sekolah/laporan/'.$rep->id)->assertStatus(404);
        // Claim + forward -> kepsek tetap boleh
        $this->actingAs($guruA)->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('success');
        $this->actingAs($guruA)->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'Butuh kepsek.'])->assertSessionHas('success');
        $this->actingAs($kepsek)->get('/kepala-sekolah/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(200);
        // Mismatch report/attachment -> 404
        $rep2 = $this->createReport('F142-K2');
        $att2 = $rep2->attachments()->firstOrFail();
        $this->actingAs($kepsek)->get('/kepala-sekolah/laporan/'.$rep->id.'/attachment/'.$att2->id)->assertStatus(404);
    }

    public function test_upload_still_validated_and_private(): void
    {
        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        // Tanpa foto ditolak; 6 foto ditolak; >8MB ditolak (validasi utuh)
        $this->actingAs($siswaA)->post('/siswa/laporan', [
            'title' => 'F142-V', 'category_id' => $this->ids['cat'], 'location' => 'L',
            'description' => 'D',
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertSessionHasErrors('photos');
        $rep = $this->createReport('F142-VOK');
        $att = $rep->attachments()->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($att->file_patch));
        $this->assertFalse(Storage::disk('public')->exists($att->file_patch));
    }
}

