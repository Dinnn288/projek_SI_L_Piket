<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportCategory;
use App\Models\ReportHistory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\UserBaru;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * B3.7 — hardening disk privat: route serve otomatis framework untuk disk
 * `local` dimatikan (`serve => false`).
 *
 * ISOLASI: pola reconnect + assert nama DB, mengikuti
 * AttachmentSecurityFase142Test. Filesystem memakai Storage::fake.
 */
class PrivateDiskServeB37Test extends TestCase
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
        $tmp = tempnam(sys_get_temp_dir(), 'b37').'.png';
        file_put_contents($tmp, $png);
        clearstatcache(true, $tmp);

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Storage::fake('local');

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_B37'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_B37')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_B37', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_B37'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswaA = $mkUser('b37_siswa_a', 'Siswa A B37', 'siswa');
        $siswaB = $mkUser('b37_siswa_b', 'Siswa B B37', 'siswa');
        if (! Student::where('user_id', $siswaA->id)->exists()) {
            Student::create(['user_id' => $siswaA->id, 'nis' => 'B37NIS_A', 'class_id' => $class->id]);
        }
        if (! Student::where('user_id', $siswaB->id)->exists()) {
            Student::create(['user_id' => $siswaB->id, 'nis' => 'B37NIS_B', 'class_id' => $class->id]);
        }

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
                $sids = Student::whereIn('nis', ['B37NIS_A', 'B37NIS_B'])->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::whereIn('nis', ['B37NIS_A', 'B37NIS_B'])->delete();
                UserBaru::whereIn('username', ['b37_siswa_a', 'b37_siswa_b'])->delete();
                ReportCategory::whereKey($this->ids['cat'] ?? 0)->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
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

    public function test_1_official_endpoint_still_serves_authorized_and_rejects_other(): void
    {
        $rep = $this->createReport('B37-OWN');
        $att = $rep->attachments()->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($att->file_patch));

        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        $res = $this->actingAs($siswaA)->get('/siswa/laporan/'.$rep->id.'/attachment/'.$att->id);
        $res->assertStatus(200);
        $this->assertStringStartsWith('image/', (string) $res->headers->get('Content-Type'));
        $this->assertSame('nosniff', (string) $res->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('inline', (string) $res->headers->get('Content-Disposition'));

        $siswaB = UserBaru::where('username', 'b37_siswa_b')->firstOrFail();
        $this->actingAs($siswaB)->get('/siswa/laporan/'.$rep->id.'/attachment/'.$att->id)->assertStatus(404);
    }

    public function test_2_auto_serve_routes_disabled_for_private_disk(): void
    {
        // Tanpa cache konfigurasi lama: route dibaca dari config aktual.
        $this->assertFalse($this->app->configurationIsCached(), 'Config tidak boleh dari cache lama.');
        $this->assertFalse((bool) config('filesystems.disks.local.serve'), 'Disk local harus serve=false.');

        $this->assertFalse(Route::has('storage.local'), 'Route storage.local tidak boleh terdaftar.');
        $this->assertFalse(Route::has('storage.local.upload'), 'Route storage.local.upload tidak boleh terdaftar.');

        $storageRoutes = collect(Route::getRoutes())->filter(
            fn ($r) => str_starts_with($r->uri(), 'storage/')
        );
        $this->assertCount(0, $storageRoutes, 'Tidak boleh ada route storage/* otomatis.');
    }

    public function test_3_upload_storage_flow_and_public_disk_unchanged(): void
    {
        $rep = $this->createReport('B37-UP');
        $att = $rep->attachments()->firstOrFail();
        $this->assertStringStartsWith('reports/', $att->file_patch);
        $this->assertTrue(Storage::disk('local')->exists($att->file_patch));
        $this->assertFalse(Storage::disk('public')->exists($att->file_patch));

        $this->assertSame('local', config('filesystems.disks.public.driver'));
        $this->assertSame('public', config('filesystems.disks.public.visibility'));
        $this->assertSame('/storage', parse_url((string) config('filesystems.disks.public.url'), PHP_URL_PATH));
        $root = str_replace('\\', '/', (string) config('filesystems.disks.public.root'));
        $this->assertStringEndsWith('app/public', $root);

        $links = config('filesystems.links');
        $this->assertArrayHasKey(public_path('storage'), $links);
        $this->assertSame(storage_path('app/public'), $links[public_path('storage')]);
    }
}
