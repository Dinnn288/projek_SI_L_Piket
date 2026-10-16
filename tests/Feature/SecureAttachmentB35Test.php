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
use App\Support\SecureAttachment;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * B3.5 — hardening lampiran: path dikungkung di reports/ + header aman.
 *
 * ISOLASI: pola reconnect + assert nama DB, mengikuti
 * AttachmentSecurityFase142Test.
 */
class SecureAttachmentB35Test extends TestCase
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

    private function makePhoto(string $name, string $kind = 'png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $webp = base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA');
        // JPEG 1x1 piksel valid (JFIF + SOF0 + SOS + EOI).
        $jpegHex = 'ffd8ffe000104a46494600010100000100010000ffdb0043000302020302020303030304030304050805050404050a070706080c0a0c0c0b0a0b0b0d0e12100d0e0f0f0e0d11100e111314151617181a191b1c1d1e1f1f1e1d1c1b1a19181716151413001ffdb0043010304040504050905050914130d0a0d14100e0b0c0e12121313141316151417141216171819222317232417150f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0f0ffc00011080001000103012200021101031101ffc4001f0000010501010101010100000000000000000102030405060708090a0bffc400b5100002010303020403050504040000017d0102030004110512213141328191a1071424815a161223242e170a162434e125f1171819a1a26343572c3a33217093a2000000022222222222222222222222222222222222222222222222222222ffc40014010100000000000000000000000000000000000000ffc4001f01000103010101010101010100000000000000000102030405060708090a0bffda000c03010002110311003f00f7fa28a2803fffd9';
        $bytes = $kind === 'webp' ? $webp : ($kind === 'jpg' ? hex2bin($jpegHex) : $png);
        $ext = $kind === 'jpg' ? 'jpg' : $kind;
        $tmp = tempnam(sys_get_temp_dir(), 'b35').'.'.$ext;
        file_put_contents($tmp, $bytes);
        clearstatcache(true, $tmp);

        return new UploadedFile($tmp, $name, null, null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_B35'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_B35')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_B35', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_B35'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswaA = $mkUser('b35_siswa_a', 'Siswa A B35', 'siswa');
        $siswaB = $mkUser('b35_siswa_b', 'Siswa B B35', 'siswa');
        if (! Student::where('user_id', $siswaA->id)->exists()) {
            Student::create(['user_id' => $siswaA->id, 'nis' => 'B35NIS_A', 'class_id' => $class->id]);
        }
        if (! Student::where('user_id', $siswaB->id)->exists()) {
            Student::create(['user_id' => $siswaB->id, 'nis' => 'B35NIS_B', 'class_id' => $class->id]);
        }
        $today = GuruDutyService::todayDay() ?? 'senin';
        foreach ([['b35_guru_a', 'B35NIP_A'], ['b35_guru_b', 'B35NIP_B']] as [$u, $nip]) {
            $gu = $mkUser($u, "Guru $u", 'guru');
            $teacher = Teacher::where('user_id', $gu->id)->first()
                ?: Teacher::create(['user_id' => $gu->id, 'nip' => $nip]);
            if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
                DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
            }
        }
        $mkUser('b35_kepsek', 'Kepsek B35', 'kepala_sekolah');

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
                $sids = Student::whereIn('nis', ['B35NIS_A', 'B35NIS_B'])->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::whereIn('nis', ['B35NIS_A', 'B35NIS_B'])->delete();
                $gids = UserBaru::whereIn('username', ['b35_guru_a', 'b35_guru_b'])->pluck('id');
                $tids = Teacher::whereIn('user_id', $gids)->pluck('id');
                if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->delete();
                Teacher::whereIn('user_id', $gids)->delete();
                UserBaru::whereIn('username', ['b35_siswa_a', 'b35_siswa_b', 'b35_guru_a', 'b35_guru_b', 'b35_kepsek'])->delete();
                ReportCategory::whereKey($this->ids['cat'] ?? 0)->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function createReport(string $title, string $kind = 'png'): Report
    {
        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        $this->actingAs($siswaA)->post('/siswa/laporan', [
            'title' => $title, 'category_id' => $this->ids['cat'],
            'location' => 'Kelas - lokasi', 'description' => 'Deskripsi '.$title,
            'photos' => [$this->makePhoto('a.'.$kind, $kind)],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect();

        return Report::where('title', $title)->firstOrFail();
    }

    public function test_1_owner_sees_valid_attachment_with_safe_headers(): void
    {
        $rep = $this->createReport('B35-OWN');
        $att = $rep->attachments()->firstOrFail();
        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        $res = $this->actingAs($siswaA)->get('/siswa/laporan/'.$rep->id.'/attachment/'.$att->id);
        $res->assertStatus(200);
        $this->assertStringStartsWith('image/', (string) $res->headers->get('Content-Type'));
        $this->assertSame('nosniff', (string) $res->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('inline', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_2_unauthorized_and_mismatch_rejected(): void
    {
        $repA = $this->createReport('B35-U1');
        $repB = $this->createReport('B35-U2');
        $attB = $repB->attachments()->firstOrFail();
        $siswaB = UserBaru::where('username', 'b35_siswa_b')->firstOrFail();
        $this->actingAs($siswaB)->get('/siswa/laporan/'.$repA->id.'/attachment/'.$repA->attachments()->firstOrFail()->id)->assertStatus(404);
        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        $this->actingAs($siswaA)->get('/siswa/laporan/'.$repA->id.'/attachment/'.$attB->id)->assertStatus(404);
    }

    public function test_3_traversal_absolute_and_outside_prefix_rejected(): void
    {
        $rep = $this->createReport('B35-TRAV');
        $att = $rep->attachments()->firstOrFail();
        $valid = $att->file_patch;
        $this->assertStringStartsWith('reports/', $valid);

        foreach ([
            'reports/../.env',
            'reports/../../.env',
            'reports/%2e%2e/.env',
            '/etc/passwd',
            'C:\\Windows\\win.ini',
            '..\\..\\.env',
            '.env',
            'public/reports/x.png',
            'reports/',
            'reports//x.png',
            "reports/\0.png",
        ] as $evil) {
            $att->file_patch = $evil;
            $this->assertNull(SecureAttachment::resolve($evil), "Path harus ditolak: {$evil}");
        }

        // Nilai jahat di DB juga ditolak endpoint (404, tanpa bocor path).
        $att->file_patch = 'reports/../.env';
        $att->save();
        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        $this->actingAs($siswaA)->get('/siswa/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(404);

        $att->file_patch = $valid;
        $att->save();
        $this->actingAs($siswaA)->get('/siswa/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(200);
    }

    public function test_4_missing_or_non_regular_file_rejected(): void
    {
        $rep = $this->createReport('B35-MISS');
        $att = $rep->attachments()->firstOrFail();
        $this->assertNull(SecureAttachment::resolve('reports/tidak-ada-b35.png'));

        $dir = 'reports/b35-dir-'.$rep->id;
        Storage::disk('local')->makeDirectory($dir);
        $this->assertNull(SecureAttachment::resolve($dir));
        Storage::disk('local')->deleteDirectory($dir);
    }

    public function test_5_spoofed_mime_rejected_and_headers_enforced(): void
    {
        $rep = $this->createReport('B35-SPOOF');
        $att = $rep->attachments()->firstOrFail();
        $evil = 'reports/b35-evil-'.$rep->id.'.png';
        Storage::disk('local')->put($evil, '<?php echo "x"; ?>');
        $this->assertNull(SecureAttachment::resolve($evil));
        Storage::disk('local')->delete($evil);

        $svg = 'reports/b35-evil-'.$rep->id.'.svg';
        Storage::disk('local')->put($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->assertNull(SecureAttachment::resolve($svg));
        Storage::disk('local')->delete($svg);

        // MIME asli menang atas nama jahat: file PNG valid walau nama .php.
        $realPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $odd = 'reports/b35-odd-'.$rep->id.'.php';
        Storage::disk('local')->put($odd, $realPng);
        $resolved = SecureAttachment::resolve($odd);
        $this->assertNotNull($resolved);
        $this->assertSame('image/png', $resolved['mime']);
        $response = SecureAttachment::response($resolved);
        $this->assertStringStartsWith('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
        Storage::disk('local')->delete($odd);
    }

    public function test_6_valid_jpg_png_webp_served(): void
    {
        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        foreach (['jpg', 'png', 'webp'] as $kind) {
            $rep = $this->createReport('B35-FMT-'.$kind, $kind);
            $att = $rep->attachments()->firstOrFail();
            $res = $this->actingAs($siswaA)->get('/siswa/laporan/'.$rep->id.'/attachment/'.$att->id);
            $res->assertStatus(200);
            $this->assertStringStartsWith('image/', (string) $res->headers->get('Content-Type'));
            $this->assertSame('nosniff', (string) $res->headers->get('X-Content-Type-Options'));
        }
    }

    public function test_7_guru_and_kepsek_no_regression(): void
    {
        $rep = $this->createReport('B35-ROLES');
        $att = $rep->attachments()->firstOrFail();
        $guruA = UserBaru::where('username', 'b35_guru_a')->firstOrFail();
        $guruB = UserBaru::where('username', 'b35_guru_b')->firstOrFail();
        $kepsek = UserBaru::where('username', 'b35_kepsek')->firstOrFail();

        $this->actingAs($guruA)->get('/guru/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(200);
        $this->actingAs($kepsek)->get('/kepala-sekolah/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(404);

        $this->actingAs($guruA)->post('/guru/laporan/'.$rep->id.'/claim')->assertSessionHas('success');
        $this->actingAs($guruB)->get('/guru/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(404);
        $res = $this->actingAs($guruA)->get('/guru/laporan/'.$rep->id.'/attachment/'.$att->id);
        $res->assertStatus(200);
        $this->assertSame('nosniff', (string) $res->headers->get('X-Content-Type-Options'));

        $this->actingAs($guruA)->post('/guru/laporan/'.$rep->id.'/forward', ['note' => 'Butuh kepsek.'])->assertSessionHas('success');
        $resK = $this->actingAs($kepsek)->get('/kepala-sekolah/laporan/'.$rep->id.'/attachment/'.$att->id);
        $resK->assertStatus(200);
        $this->assertSame('nosniff', (string) $resK->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('image/', (string) $resK->headers->get('Content-Type'));
    }
}
