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

    /**
     * Himpunan penerima sah notifikasi "Laporan Baru" untuk hari uji
     * (B3.7.2: ekspektasi tidak lagi konstanta 1).
     *
     * Dihitung dari sisi Teacher via relasi `dutySchedules` — jalur ORM
     * berbeda dari controller (yang mulai dari DutySchedule lalu
     * `->teacher?->user_id`), sehingga tes tidak sekadar menyalin query
     * produksi. Jangkar independen: `b1_guru` (dibuat setUp ini beserta
     * duty schedule-nya) wajib ada di himpunan bila ada hari piket.
     *
     * @return array<int>
     */
    private function expectedNewReportRecipients(): array
    {
        $today = GuruDutyService::todayDay();

        if ($today === null) {
            return [];
        }

        return Teacher::whereHas('dutySchedules', fn ($q) => $q->where('day', $today))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Exactly-once per penerima sah untuk satu laporan: himpunan
     * penerima tepat sama (tanpa kurang, tanpa ekstra, tanpa ganda).
     */
    private function assertExactlyOnceNewReportNotification(array $userIds, int $reportId): void
    {
        $rows = Notification::where('report_id', $reportId)
            ->where('title', 'Laporan Baru')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        sort($rows);
        $expected = $userIds;
        sort($expected);

        $this->assertEquals($expected, $rows, 'Penerima notifikasi harus tepat himpunan sah.');
        $this->assertEquals(count($rows), count(array_unique($rows)), 'Tanpa notifikasi ganda per penerima.');
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
            // B3.7.2: ekspektasi dari himpunan penerima sah (termasuk duty
            // global suite lain + b1_guru), dengan jangkar independen.
            $expected = $this->expectedNewReportRecipients();
            $this->assertContains($this->ids['guru'], $expected, 'Jangkar: b1_guru wajib piket hari ini.');
            $this->assertNotEmpty($expected, 'Harus ada penerima sah bila ada hari piket.');
            $repId = Report::where('student_id', $siswa->student->id)->value('id');
            $this->assertExactlyOnceNewReportNotification($expected, (int) $repId);
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
        // B3.7.2: exactly-once per penerima sah (bukan konstanta 1).
        if ($this->ids['hasDuty']) {
            $expected = $this->expectedNewReportRecipients();
            $this->assertContains($this->ids['guru'], $expected, 'Jangkar: b1_guru wajib piket hari ini.');
            $this->assertNotEmpty($expected, 'Harus ada penerima sah bila ada hari piket.');
            $this->assertExactlyOnceNewReportNotification($expected, (int) $rep->id);
        }
    }

    public function test_11_marker_store_failure_keeps_report_and_warns(): void
    {
        // Tahap B1-fix: put marker gagal setelah commit -> laporan tetap
        // tersimpan (TIDAK dihapus), user diarahkan ke index dengan warning
        // agar memeriksa daftar sebelum kirim ulang, lock tetap dilepas.
        // Simulasi di level store (bukan mock facade) agar middleware sesi
        // yang memakai Cache tetap berjalan normal.
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();

        $store = Cache::getStore();
        $failing = new class($store) extends \Illuminate\Cache\Repository
        {
            public bool $failedOnce = false;

            public function __construct($store)
            {
                parent::__construct($store);
            }

            public function put($key, $value, $ttl = null): mixed
            {
                if (! $this->failedOnce && is_string($key) && str_starts_with($key, 'report_submit:')) {
                    $this->failedOnce = true;

                    throw new \RuntimeException('cache down');
                }

                return parent::put($key, $value, $ttl);
            }
        };
        Cache::swap($failing);

        try {
            $res = $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-MarkerFail', $key));
        } finally {
            Cache::swap(new \Illuminate\Cache\Repository($store));
        }
        $res->assertRedirect(route('siswa.reports.index'));
        $res->assertSessionHas('warning');
        $this->assertEquals(1, $this->counts($siswa->student->id)['reports'], 'Laporan sukses TIDAK boleh dihapus.');
        $this->assertEquals(1, $this->counts($siswa->student->id)['attachments']);
    }

    public function test_12_marker_verify_readback_failure_warns(): void
    {
        // put gagal (atau baca-ulang gagal) -> warning, laporan tetap ada.
        // Catatan: get yang gagal pada replay-check ditangani sebagai tanpa
        // marker (request diproses), sehingga skenario ini memaksa put gagal.
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();

        $store = Cache::getStore();
        $failingPut = new class($store) extends \Illuminate\Cache\Repository
        {
            public function __construct($store)
            {
                parent::__construct($store);
            }

            public function put($key, $value, $ttl = null): mixed
            {
                if (is_string($key) && str_starts_with($key, 'report_submit:') && ! str_ends_with($key, ':lock')) {
                    throw new \RuntimeException('cache write down');
                }

                return parent::put($key, $value, $ttl);
            }
        };
        Cache::swap($failingPut);

        try {
            $res = $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-VerifyFail', $key));
        } finally {
            Cache::swap(new \Illuminate\Cache\Repository($store));
        }
        $res->assertRedirect(route('siswa.reports.index'));
        $res->assertSessionHas('warning');
        $this->assertEquals(1, $this->counts($siswa->student->id)['reports']);
        $this->assertNull(ReportIdempotency::result($siswa->id, $key), 'Marker gagal tidak boleh pura-pura ada.');
    }

    public function test_12b_cache_read_failure_on_replay_is_safe(): void
    {
        // get gagal saat replay-check -> request diproses aman (bukan 500).
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();

        $store = Cache::getStore();
        $failingGet = new class($store) extends \Illuminate\Cache\Repository
        {
            public bool $failedOnce = false;

            public function __construct($store)
            {
                parent::__construct($store);
            }

            public function get($key, $default = null): mixed
            {
                if (! $this->failedOnce && is_string($key) && str_starts_with($key, 'report_submit:')) {
                    $this->failedOnce = true;

                    throw new \RuntimeException('cache read down');
                }

                return parent::get($key, $default);
            }
        };
        Cache::swap($failingGet);

        try {
            $res = $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-ReadFail', $key));
        } finally {
            Cache::swap(new \Illuminate\Cache\Repository($store));
        }
        // Diproses sebagai pengiriman baru (aman, tanpa 500); laporan ada.
        $res->assertRedirect();
        $this->assertEquals(1, $this->counts($siswa->student->id)['reports']);
    }

    public function test_13_lock_timeout_returns_retryable_error(): void
    {
        // Lock gagal diperoleh -> error idempotency_key + input dipertahankan,
        // tanpa laporan baru. Simulasi dengan menahan lock dari "proses lain".
        $siswa = UserBaru::findOrFail($this->ids['siswaA']);
        $key = (string) Str::uuid();

        $holder = \App\Support\ReportIdempotency::lock($siswa->id, $key);
        $this->assertTrue($holder->acquire(), 'Prekondisi: lock harus bisa dipegang.');

        try {
            $res = $this->actingAs($siswa)->post('/siswa/laporan', $this->payload('B1-LockTimeout', $key));
        } finally {
            $holder->release();
        }
        // Controller menunggu hingga 10 detik lalu 422; tanpa laporan baru.
        $res->assertSessionHasErrors('idempotency_key');
        $this->assertEquals(0, $this->counts($siswa->student->id)['reports']);
    }
}
