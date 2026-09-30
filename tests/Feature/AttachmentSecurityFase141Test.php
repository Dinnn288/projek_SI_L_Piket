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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentSecurityFase141Test extends TestCase
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
        $tmp = tempnam(sys_get_temp_dir(), 'f141').'.png';
        file_put_contents($tmp, $png);
        clearstatcache(true, $tmp);

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F141'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F141')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F141', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F141'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $siswaA = $mkUser('f141_siswa_a', 'Siswa A F141', 'siswa');
        $siswaB = $mkUser('f141_siswa_b', 'Siswa B F141', 'siswa');
        if (! Student::where('user_id', $siswaA->id)->exists()) {
            Student::create(['user_id' => $siswaA->id, 'nis' => 'F141NIS_A', 'class_id' => $class->id]);
        }
        if (! Student::where('user_id', $siswaB->id)->exists()) {
            Student::create(['user_id' => $siswaB->id, 'nis' => 'F141NIS_B', 'class_id' => $class->id]);
        }

        $this->ids = [
            'major' => $major->id, 'class' => $class->id, 'cat' => $cat->id,
            'siswaA' => $siswaA->id, 'siswaB' => UserBaru::where('username', 'f141_siswa_b')->value('id'),
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $sids = Student::whereIn('nis', ['F141NIS_A', 'F141NIS_B'])->pluck('id');
                $rids = Report::whereIn('student_id', $sids)->pluck('id');
                if ($rids->isNotEmpty()) {
                    $paths = ReportAttachment::whereIn('report_id', $rids)->pluck('file_patch');
                    foreach ($paths as $p) {
                        Storage::disk('local')->delete($p);
                    }
                    Notification::whereIn('report_id', $rids)->delete();
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                Student::whereIn('nis', ['F141NIS_A', 'F141NIS_B'])->delete();
                UserBaru::whereIn('username', ['f141_siswa_a', 'f141_siswa_b'])->delete();
                ReportCategory::whereKey($this->ids['cat'] ?? 0)->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    public function test_page_authorization_and_direct_url_evidence(): void
    {
        // JANGAN gunakan Storage::fake agar file fisik nyata untuk uji direct URL.
        $siswaA = UserBaru::findOrFail($this->ids['siswaA']);
        $siswaB = UserBaru::where('username', 'f141_siswa_b')->firstOrFail();
        $this->actingAs($siswaA)->post('/siswa/laporan', [
            'title' => 'F141 Report', 'category_id' => $this->ids['cat'],
            'location' => 'Kelas - lokasi', 'description' => 'Deskripsi F141',
            'photos' => [$this->makePhoto('bukti.png')],
        ])->assertRedirect();
        $report = Report::where('title', 'F141 Report')->firstOrFail();
        $att = $report->attachments()->firstOrFail();

        // TEST A halaman: pemilik 200, siswa lain ditolak, guest redirect.
        $this->actingAs($siswaA)->get('/siswa/laporan/'.$report->id)->assertStatus(200);
        $pageOther = $this->actingAs($siswaB)->get('/siswa/laporan/'.$report->id);
        $this->assertContains($pageOther->getStatusCode(), [403, 404]);
        $this->refreshApplication();
        $this->reconnect();
        $this->get('/siswa/laporan/'.$report->id)->assertRedirect('/login');

        // TEST B pasca-fix 14.2: file HANYA di disk private, tidak ada
        // di disk public; tidak ada URL publik langsung.
        $this->assertTrue(Storage::disk('local')->exists($att->file_patch));
        $this->assertFalse(Storage::disk('public')->exists($att->file_patch));

        // Endpoint attachment berotorisasi kini tersedia per role.
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())->map(fn ($r) => $r->uri())->all();
        $hasProtectedAttachmentRoute = collect($routes)->contains(fn ($u) => str_contains($u, 'attachment'));
        $this->assertTrue($hasProtectedAttachmentRoute, 'Endpoint attachment berotorisasi harus ada.');
    }
}




