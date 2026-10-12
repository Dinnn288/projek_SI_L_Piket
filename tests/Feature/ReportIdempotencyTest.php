<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportCategory;
use App\Models\ReportHistory;
use App\Models\DutySchedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Services\GuruDutyService;
use App\Support\ReportIdempotency;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * B1 — pencegahan duplikasi laporan.
 *
 * ISOLASI: test ini memakai database `si_l_piket_test` (bukan `si_l_piket`
 * aktif) + `Storage::fake`. Seluruh tulis terjadi di DB test; DB bisnis
 * tidak disentuh. Prasyarat: database test sudah dimigrasi penuh
 * (lihat laporan B1). Tanpa migrasi/seed ke DB aktif.
 */
class ReportIdempotencyTest extends TestCase
{
    private array $ids = [];

    private function useTestDb(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket_test']);
        DB::reconnect('mysql');
        $this->assertEquals(
            'si_l_piket_test',
            DB::connection()->getDatabaseName(),
            'Pengaman: test hanya boleh berjalan di DB test.'
        );
    }

    private function makePhoto(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $tmp = tempnam(sys_get_temp_dir(), 'b1').'.png';
        file_put_contents($tmp, $png);
        clearstatcache(true, $tmp);

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->useTestDb();
        Storage::fake('local');
        Cache::flush();

        $major = Major::create(['name' => 'TEST_MAJOR_B1', 'status' => 'aktif']);
        $class = SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_B1', 'status' => 'aktif']);
        $cat = ReportCategory::create(['name' => 'TEST_Kat_B1', 'description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mk = fn (string $u, string $role) => UserBaru::create(['name' => "Nama $u", 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        $siswaA = $mk('b1_siswa_a', 'siswa');
        Student::create(['user_id' => $siswaA->id, 'nis' => 'B1NIS_A', 'class_id' => $class->id]);
        $siswaB = $mk('b1_siswa_b', 'siswa');
        Student::create(['user_id' => $siswaB->id, 'nis' => 'B1NIS_B', 'class_id' => $class->id]);
        // Guru piket hari ini agar notifikasi Laporan Baru punya penerima
        // (validasi perilaku "notifikasi hanya sekali" butuh recipient).
        $guru = $mk('b1_guru', 'guru');
        $teacher = Teacher::create(['user_id' => $guru->id, 'nip' => 'B1NIP001']);
        $today = GuruDutyService::todayDay();
        if ($today !== null) {
            DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
        }

        $this->ids = ['major' => $major->id, 'class' => $class->id, 'cat' => $cat->id, 'siswaA' => $siswaA->id, 'siswaB' => $siswaB->id, 'guru' => $guru->id, 'hasDuty' => $today !== null];
    }

    protected function tearDown(): void
    {
        try {
            $this->useTestDb();
            if (! empty($this->ids)) {
                $sids = Student::whereIn('nis', ['B1NIS_A', 'B1NIS_B'])->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::whereIn('nis', ['B1NIS_A', 'B1NIS_B'])->delete();
                $gids = UserBaru::whereIn('username', ['b1_guru'])->pluck('id');
                $tids = Teacher::whereIn('user_id', $gids)->pluck('id');
                if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->delete();
                Teacher::whereIn('user_id', $gids)->delete();
                UserBaru::whereIn('username', ['b1_siswa_a', 'b1_siswa_b', 'b1_guru'])->delete();
                ReportCategory::whereKey($this->ids['cat'] ?? 0)->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
            Cache::flush();
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function payload(string $title, string $key, int $n = 1): array
    {
        $photos = [];
        for ($i = 0; $i < $n; $i++) {
            $photos[] = $this->makePhoto("f$i.png");
        }

        return [
            'title' => $title, 'category_id' => $this->ids['cat'],
            'location' => 'Lokasi uji', 'description' => 'Deskripsi '.$title,
            'photos' => $photos, 'idempotency_key' => $key,
        ];
    }

    private function counts(int $studentId): array
    {
        return [
            'reports' => Report::where('student_id', $studentId)->count(),
            'attachments' => ReportAttachment::whereIn('report_id', Report::where('student_id', $studentId)->pluck('id'))->count(),
            'histories' => ReportHistory::whereIn('report_id', Report::where('student_id', $studentId)->pluck('id'))->count(),
            'notifications' => Notification::whereIn('report_id', Report::where('student_id', $studentId)->pluck('id'))->count(),
        ];
    }

    public function test_1_normal_submit_creates_one_report(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();
        $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-Normal', $key))
            ->assertRedirect();
        $c = $this->counts($siswa->student->id);
        $this->assertEquals(1, $c['reports']);
        $this->assertEquals(1, $c['attachments']);
        $rep = Report::where('student_id', $siswa->student->id)->firstOrFail();
        $stored = ReportIdempotency::result($siswa->id, $key);
        $this->assertNotNull($stored);
        $this->assertEquals($rep->id, $stored['report_id']);
    }

    public function test_2_repeat_same_key_creates_no_duplicate(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();
        $p1 = $this->payload('B1-Repeat', $key);
        $first = $this->actingAs($siswa)->post('/siswa/laporan', $p1)->assertRedirect();
        $p2 = $this->payload('B1-Repeat', $key);
        $second = $this->actingAs($siswa)->post('/siswa/laporan', $p2)->assertRedirect();
        // Replay mengarah ke laporan yang sama.
        $this->assertEquals($first->headers->get('Location'), $second->headers->get('Location'));
        $c = $this->counts($siswa->student->id);
        $this->assertEquals(1, $c['reports'], 'Retry key sama tidak boleh duplikat.');
        $this->assertEquals(1, $c['attachments']);
        if ($this->ids['hasDuty']) {
            $this->assertEquals(1, Notification::where('title', 'Laporan Baru')->whereIn('report_id', Report::where('student_id', $siswa->student->id)->pluck('id'))->count(), 'Notifikasi hanya sekali.');
        }
    }

    public function test_3_concurrent_same_key_no_duplicate(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();
        // Dua request "bersamaan": keduanya memakai key sama. Lock atomik
        // memastikan hanya satu yang memproses; yang lain replay.
        $r1 = $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-Race', $key))->assertRedirect();
        $r2 = $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-Race', $key))->assertRedirect();
        $this->assertEquals($r1->headers->get('Location'), $r2->headers->get('Location'));
        $this->assertEquals(1, $this->counts($siswa->student->id)['reports']);
    }

    public function test_4_retry_after_success_replays_same_report(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();
        $first = $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-Retry', $key))->assertRedirect();
        // Simulasi retry setelah koneksi terputus (response pertama hilang):
        // kirim ulang key sama -> replay ke laporan pertama.
        $retry = $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-Retry', $key))->assertRedirect();
        $this->assertEquals($first->headers->get('Location'), $retry->headers->get('Location'));
        $this->assertEquals(1, $this->counts($siswa->student->id)['reports']);
    }

    public function test_5_failed_transaction_leaves_no_partial(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();
        // Kategori nonaktif -> ditolak sebelum transaksi; tanpa sisa.
        $bad = $this->payload('B1-Fail', $key);
        $bad['category_id'] = 999999;
        $this->actingAs($siswa)->post('/siswa/laporan', $bad)->assertSessionHasErrors();
        $this->assertEquals(0, $this->counts($siswa->student->id)['reports']);
        $this->assertNull(ReportIdempotency::result($siswa->id, $key), 'Gagal tidak boleh menyimpan marker sukses.');
        // Retry dengan key sama + payload benar tetap bisa membuat satu laporan.
        $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-Fail', $key))->assertRedirect();
        $this->assertEquals(1, $this->counts($siswa->student->id)['reports']);
    }

    public function test_6_different_keys_allow_different_reports(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-K1', (string) Str::uuid()))->assertRedirect();
        $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-K2', (string) Str::uuid()))->assertRedirect();
        $this->assertEquals(2, $this->counts($siswa->student->id)['reports']);
    }

    public function test_7_other_user_key_cannot_access_result(): void
    {
        $a = UserBaru::findOrFail($this->ids['siswaA']);
        $b = UserBaru::findOrFail($this->ids['siswaB']);
        $key = (string) Str::uuid();
        $this->actingAs($a)->post('/siswa/laporan', $this->payload('B1-Priv', $key))->assertRedirect();
        $repA = Report::where('student_id', $a->student->id)->firstOrFail();
        // Pengguna lain memakai key sama -> key terikat user B (prefix
        // user_id) sehingga dianggap pengiriman BARU milik B, bukan
        // membaca hasil milik A.
        $this->actingAs($b)->post('/siswa/laporan', $this->payload('B1-Priv', $key))->assertRedirect();
        $repB = Report::where('student_id', $b->student->id)->firstOrFail();
        $this->assertNotEquals($repA->id, $repB->id);
        $this->assertEquals($repA->student_id, $a->student->id);
        $this->assertEquals($repB->student_id, $b->student->id);
    }

    public function test_8_same_key_different_payload_rejected(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();
        $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-Orig', $key))->assertRedirect();
        $diff = $this->payload('B1-DIFF-TITLE', $key);
        $this->actingAs($siswa)->post('/siswa/laporan', $diff)->assertSessionHasErrors('idempotency_key');
        $this->assertEquals(1, $this->counts($siswa->student->id)['reports'], 'Payload beda tidak boleh membuat laporan kedua.');
    }

    public function test_9_validation_still_enforced(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        // Tanpa foto, 6 foto, kategori tak ada, key tak valid.
        $p = $this->payload('B1-V', (string) Str::uuid(), 0);
        $this->actingAs($siswa)->post('/siswa/laporan', $p)->assertSessionHasErrors('photos');
        $p6 = $this->payload('B1-V6', (string) Str::uuid(), 6);
        $this->actingAs($siswa)->post('/siswa/laporan', $p6)->assertSessionHasErrors('photos');
        $bad = $this->payload('B1-VC', (string) Str::uuid());
        $bad['category_id'] = 999999;
        $this->actingAs($siswa)->post('/siswa/laporan', $bad)->assertSessionHasErrors();
        $nokey = $this->payload('B1-VK', '!!!bukan-uuid!!!');
        $this->actingAs($siswa)->post('/siswa/laporan', $nokey)->assertSessionHasErrors('idempotency_key');
        $this->assertEquals(0, $this->counts($siswa->student->id)['reports']);
    }

    public function test_10_history_and_notification_once(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();
        $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-HN', $key))->assertRedirect();
        $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-HN', $key))->assertRedirect();
        $rep = Report::where('student_id', $siswa->student->id)->firstOrFail();
        // Tidak ada history created (perilaku existing dipertahankan).
        $this->assertEquals(0, ReportHistory::where('report_id', $rep->id)->count());
        // Notifikasi Laporan Baru tepat satu bila ada guru piket hari ini.
        if ($this->ids['hasDuty']) {
            $this->assertEquals(1, Notification::where('report_id', $rep->id)->where('title', 'Laporan Baru')->count());
        }
    }
}
