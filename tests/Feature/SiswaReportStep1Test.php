<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\UserBaru;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiswaReportStep1Test extends TestCase
{
    protected static array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket_test']);
        DB::reconnect('mysql');
        \Tests\TestDatabaseGuard::assertTestDatabaseSafe();
        Storage::fake('local');

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F6S1'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F6S1')->first();
        if (! $class) {
            $class = SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F6S1', 'status' => 'aktif']);
        }
        $catKeributan = ReportCategory::firstOrCreate(['name' => 'TEST_Keributan_F6S1'], ['description' => 'tes', 'status' => 'aktif', 'priority' => 'tinggi']);
        $catFasilitas = ReportCategory::firstOrCreate(['name' => 'TEST_Fasilitas_F6S1'], ['description' => 'tes', 'status' => 'aktif', 'priority' => 'sedang']);
        $catKebersihan = ReportCategory::firstOrCreate(['name' => 'TEST_Kebersihan_F6S1'], ['description' => 'tes', 'status' => 'aktif', 'priority' => 'rendah']);

        $siswaA = UserBaru::where('username', 'f6s1_siswa_a')->first();
        if (! $siswaA) {
            $siswaA = UserBaru::create(['name' => 'Siswa A F6S1', 'username' => 'f6s1_siswa_a', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        }
        $siswaB = UserBaru::firstOrCreate(['username' => 'f6s1_siswa_b'], ['name' => 'Siswa B F6S1', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $guru = UserBaru::where('role', 'guru')->where('status', 'aktif')->first();
        $kepsek = UserBaru::where('role', 'kepala_sekolah')->where('status', 'aktif')->first();
        $nonaktif = UserBaru::firstOrCreate(['username' => 'f6s1_nonaktif'], ['name' => 'Nonaktif F6S1', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'nonaktif']);

        $stA = Student::where('user_id', $siswaA->id)->first();
        if (! $stA) {
            // Sisa run sebelumnya: user lama tanpa student tetapi NIS dipakai
            // student yatim ber-reports (RESTRICT). Pakai ulang student sisa
            // bila kelasnya cocok; hanya buat baru bila NIS benar-benar bebas.
            $orphan = Student::where('nis', 'F6S1NIS001')->first();
            if ($orphan && (int) $orphan->class_id === (int) $class->id) {
                $orphan->user_id = $siswaA->id;
                $orphan->phone = '081';
                $orphan->save();
                $stA = $orphan->fresh();
            } else {
                Student::where('nis', 'F6S1NIS001')->whereDoesntHave('reports')->delete();
                $stA = Student::create(['user_id' => $siswaA->id, 'nis' => 'F6S1NIS001', 'class_id' => $class->id, 'phone' => '081']);
            }
        }
        $stB = Student::where('user_id', $siswaB->id)->first();
        if (! $stB) {
            $stB = Student::create(['user_id' => $siswaB->id, 'nis' => 'F6S1NIS002', 'class_id' => $class->id, 'phone' => '082']);
        }

        {

            self::$ids = [
                'major' => $major->id, 'class' => $class->id,
                'catK' => $catKeributan->id, 'catF' => $catFasilitas->id, 'catB' => $catKebersihan->id,
                'siswaA' => $siswaA->id, 'siswaB' => $siswaB->id, 'nonaktif' => $nonaktif->id,
                'stA' => $stA->id, 'stB' => $stB->id,
                'admin' => $admin?->id, 'guru' => $guru?->id, 'kepsek' => $kepsek?->id,
            ];
        }
    }

    protected function tearDown(): void
    {
        // Cleanup per-test terhadap laporan uji. Dibungkus try agar tidak
        // mengganggu hasil test bila konfigurasi DB di-reset mid-test.
        try {
            config(['database.default' => 'mysql']);
            DB::purge('mysql');
            config(['database.connections.mysql.database' => 'si_l_piket_test']);
            DB::reconnect('mysql');
            \Tests\TestDatabaseGuard::assertTestDatabaseSafe();
            $stA = Student::where('user_id', UserBaru::where('username', 'f6s1_siswa_a')->value('id'))->value('id');
            $stB = Student::where('user_id', UserBaru::where('username', 'f6s1_siswa_b')->value('id'))->value('id');
            $reportIds = Report::whereIn('student_id', array_filter([$stA, $stB]))->pluck('id');
            if ($reportIds->isNotEmpty()) {
                ReportAttachment::whereIn('report_id', $reportIds)->delete();
                Report::whereIn('id', $reportIds)->delete();
            }
        } catch (\Throwable $e) {
            // Abaikan: cleanup manual tersedia via cleanupTestMasters().
        }
        parent::tearDown();
    }

    public static function cleanupTestMasters(): void
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $reportIds = App\Models\Report::whereHas('student', fn ($q) => $q->whereIn('nis', ['F6S1NIS001', 'F6S1NIS002']))->pluck('id');
        if ($reportIds->isNotEmpty()) {
            App\Models\ReportAttachment::whereIn('report_id', $reportIds)->delete();
            App\Models\Report::whereIn('id', $reportIds)->delete();
        }
        App\Models\Student::whereIn('nis', ['F6S1NIS001', 'F6S1NIS002'])->delete();
        App\Models\UserBaru::whereIn('username', ['f6s1_siswa_a', 'f6s1_siswa_b', 'f6s1_nonaktif'])->delete();
        App\Models\ReportCategory::whereIn('name', ['TEST_Keributan_F6S1', 'TEST_Fasilitas_F6S1', 'TEST_Kebersihan_F6S1'])->delete();
        App\Models\SchoolClass::where('name', 'TEST_KELAS_F6S1')->delete();
        App\Models\Major::where('name', 'TEST_MAJOR_F6S1')->delete();
    }

    private function siswaA()
    {
        return UserBaru::find(self::$ids['siswaA']);
    }

    private function siswaB()
    {
        return UserBaru::find(self::$ids['siswaB']);
    }

    private function makePhoto(string $name, int $kilobytes = 100): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $tmp = tempnam(sys_get_temp_dir(), 'f6s1').'.png';
        file_put_contents($tmp, $png);
        $current = filesize($tmp);
        $target = $kilobytes * 1024;
        if ($target > $current) {
            $fh = fopen($tmp, 'ab');
            fwrite($fh, str_repeat("\0", $target - $current));
            fclose($fh);
            clearstatcache(true, $tmp);
        }

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    private function payload($catId, $n = 1)
    {
        $photos = [];
        for ($i = 0; $i < $n; $i++) {
            $photos[] = $this->makePhoto("foto{$i}.png", 100);
        }

        return [
            'title' => 'Lampu kelas mati',
            'category_id' => $catId,
            'location' => 'TEST_KELAS_F6S1 - depan papan tulis',
            'description' => 'Lampu depan papan tulis mati sejak pagi.',
            'photos' => $photos,
            // B1: idempotency key wajib — tiap payload baru dapat key unik.
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ];
    }

    public function test_dashboard_index_create_show_ok(): void
    {
        $this->actingAs($this->siswaA())->get('/siswa')->assertStatus(200);
        $this->actingAs($this->siswaA())->get('/siswa/laporan')->assertStatus(200);
        $this->actingAs($this->siswaA())->get('/siswa/laporan/create')->assertStatus(200);
    }

    public function test_store_valid_1_foto(): void
    {
        $res = $this->actingAs($this->siswaA())->post('/siswa/laporan', $this->payload(self::$ids['catK'], 1));
        $res->assertRedirect();
        $report = Report::where('student_id', self::$ids['stA'])->latest('id')->first();
        $this->assertNotNull($report);
        $this->assertEquals('menunggu', $report->status);
        $this->assertEquals('tinggi', $report->priority);
        $this->assertEquals(self::$ids['class'], (int) $report->class_id);
        $this->assertEquals(1, $report->attachments()->count());
        $att = $report->attachments()->first();
        $this->assertEquals($report->id, $att->report_id);
        Storage::disk('local')->assertExists($att->file_patch);
    }

    public function test_store_valid_5_foto(): void
    {
        $res = $this->actingAs($this->siswaA())->post('/siswa/laporan', $this->payload(self::$ids['catF'], 5));
        $res->assertRedirect();
        $report = Report::where('student_id', self::$ids['stA'])->latest('id')->first();
        $this->assertEquals(5, $report->attachments()->count());
        $this->assertEquals('sedang', $report->priority);
    }

    public function test_reject_tanpa_foto_6_foto_besar_invalid(): void
    {
        $base = $this->payload(self::$ids['catK'], 1);
        unset($base['photos']);
        $this->actingAs($this->siswaA())->post('/siswa/laporan', $base)->assertSessionHasErrors('photos');

        $this->actingAs($this->siswaA())->post('/siswa/laporan', $this->payload(self::$ids['catK'], 6))->assertSessionHasErrors('photos');

        $big = $this->payload(self::$ids['catK'], 1);
        $big['photos'] = [$this->makePhoto('big.png', 9000)];
        $this->actingAs($this->siswaA())->post('/siswa/laporan', $big)->assertSessionHasErrors('photos.0');

        $badCat = $this->payload(999999, 1);
        $this->actingAs($this->siswaA())->post('/siswa/laporan', $badCat)->assertSessionHasErrors('category_id');

        $nonImg = $this->payload(self::$ids['catK'], 1);
        $nonImg['photos'] = [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')];
        $this->actingAs($this->siswaA())->post('/siswa/laporan', $nonImg)->assertSessionHasErrors('photos.0');
    }

    public function test_idor_ditolak(): void
    {
        $this->actingAs($this->siswaA())->post('/siswa/laporan', $this->payload(self::$ids['catK'], 1))->assertRedirect();
        $report = Report::where('student_id', self::$ids['stA'])->latest('id')->first();
        $this->actingAs($this->siswaA())->get('/siswa/laporan/'.$report->id)->assertStatus(200);
        $this->actingAs($this->siswaB())->get('/siswa/laporan/'.$report->id)->assertStatus(404);
    }

    public function test_manipulasi_field_diabaikan(): void
    {
        $p = $this->payload(self::$ids['catK'], 1);
        $p['student_id'] = self::$ids['stB'];
        $p['class_id'] = 999999;
        $p['priority'] = 'rendah';
        $p['status'] = 'selesai';
        $p['assigned_teacher_id'] = 1;
        $this->actingAs($this->siswaA())->post('/siswa/laporan', $p)->assertRedirect();
        $report = Report::where('student_id', self::$ids['stA'])->latest('id')->first();
        $this->assertEquals(self::$ids['stA'], (int) $report->student_id);
        $this->assertEquals(self::$ids['class'], (int) $report->class_id);
        $this->assertEquals('tinggi', $report->priority);
        $this->assertEquals('menunggu', $report->status);
        $this->assertNull($report->assigned_teacher_id);
    }

    public function test_authorization_roles(): void
    {
        $admin = UserBaru::find(self::$ids['admin']);
        $guru = UserBaru::find(self::$ids['guru']);
        $kepsek = UserBaru::find(self::$ids['kepsek']);
        if ($admin) $this->actingAs($admin)->get('/siswa/laporan')->assertStatus(403);
        if ($guru) $this->actingAs($guru)->get('/siswa/laporan')->assertStatus(403);
        if ($kepsek) $this->actingAs($kepsek)->get('/siswa/laporan')->assertStatus(403);
        $this->refreshApplication();
        Storage::fake('local');
        $this->get('/siswa/laporan')->assertRedirect('/login');
        $this->get('/siswa')->assertRedirect('/login');
    }

    public function test_login_logout_flows(): void
    {
        $this->post('/login', ['username' => 'f6s1_siswa_a', 'password' => 'password123'])->assertRedirect('/siswa');
        $this->refreshApplication();
        $this->post('/login', ['username' => 'f6s1_nonaktif', 'password' => 'password123']);
        $this->assertGuest();
    }
}




