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

class SiswaReportStep2Test extends TestCase
{
    private function makePhoto(string $name, int $kilobytes = 100): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $tmp = tempnam(sys_get_temp_dir(), 'f6s2').'.png';
        file_put_contents($tmp, $png);
        $target = $kilobytes * 1024;
        $current = filesize($tmp);
        if ($target > $current) {
            $fh = fopen($tmp, 'ab');
            fwrite($fh, str_repeat("\0", $target - $current));
            fclose($fh);
            clearstatcache(true, $tmp);
        }

        return new UploadedFile($tmp, $name, 'image/png', null, true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket_test']);
        DB::reconnect('mysql');
        \Tests\TestDatabaseGuard::assertTestDatabaseSafe();
        Storage::fake('local');

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_F6S2'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_F6S2')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_F6S2', 'status' => 'aktif']);
        $cat = ReportCategory::firstOrCreate(['name' => 'TEST_Kat_F6S2'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        foreach ([['f6s2_a', 'F6S2NIS_A'], ['f6s2_b', 'F6S2NIS_B']] as [$u, $nis]) {
            $user = UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => "U $u", 'username' => $u, 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
            if (! Student::where('user_id', $user->id)->exists()) {
                Student::create(['user_id' => $user->id, 'nis' => $nis, 'class_id' => $class->id]);
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            config(['database.default' => 'mysql']);
            DB::purge('mysql');
            config(['database.connections.mysql.database' => 'si_l_piket_test']);
            DB::reconnect('mysql');
            \Tests\TestDatabaseGuard::assertTestDatabaseSafe();
            $sids = Student::whereIn('nis', ['F6S2NIS_A', 'F6S2NIS_B'])->pluck('id');
            $rids = Report::whereIn('student_id', $sids)->pluck('id');
            if ($rids->isNotEmpty()) {
                ReportAttachment::whereIn('report_id', $rids)->delete();
                Report::whereIn('id', $rids)->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function postReport(string $username, int $catId, string $title = 'Judul'): \Illuminate\Testing\TestResponse
    {
        $user = UserBaru::where('username', $username)->firstOrFail();

        return $this->actingAs($user)->post('/siswa/laporan', [
            'title' => $title,
            'category_id' => $catId,
            'location' => 'TEST_KELAS_F6S2 - lokasi',
            'description' => 'Deskripsi '.$title,
            'photos' => [$this->makePhoto('a.png', 100)],
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    public function test_dashboard_counts_scoped(): void
    {
        $cat = ReportCategory::where('name', 'TEST_Kat_F6S2')->firstOrFail();
        $this->postReport('f6s2_a', $cat->id, 'A1');
        $this->postReport('f6s2_a', $cat->id, 'A2');
        for ($i = 1; $i <= 5; $i++) {
            $this->postReport('f6s2_b', $cat->id, "B$i");
        }

        $a = UserBaru::where('username', 'f6s2_a')->first();
        $res = $this->actingAs($a)->get('/siswa');
        $res->assertStatus(200);
        // Total milik A = 2, bukan 7
        $res->assertSee('<div class="value">2</div>', false);
        $res->assertSee('A2');
        $res->assertDontSee('B1');

        // List hanya milik A
        $list = $this->actingAs($a)->get('/siswa/laporan');
        $list->assertStatus(200);
        $list->assertSee('A1');
        $list->assertDontSee('B1');
    }

    public function test_dashboard_status_counts(): void
    {
        $cat = ReportCategory::where('name', 'TEST_Kat_F6S2')->firstOrFail();
        $this->postReport('f6s2_a', $cat->id, 'S1');
        $stA = Student::where('nis', 'F6S2NIS_A')->first();
        $rep = Report::where('student_id', $stA->id)->latest('id')->first();
        $rep->update(['status' => 'dalam_penanganan']);
        $this->postReport('f6s2_a', $cat->id, 'S2')->assertRedirect();
        $rep2 = Report::where('student_id', $stA->id)->latest('id')->first();
        $rep2->update(['status' => 'selesai']);

        $a = UserBaru::where('username', 'f6s2_a')->first();
        $res = $this->actingAs($a)->get('/siswa');
        $res->assertStatus(200);
        $res->assertSee('Menunggu');
        $res->assertSee('Diproses');
        $res->assertSee('Selesai');
    }

    public function test_detail_idor_and_404(): void
    {
        $cat = ReportCategory::where('name', 'TEST_Kat_F6S2')->firstOrFail();
        $this->postReport('f6s2_a', $cat->id, 'Milik A');
        $stA = Student::where('nis', 'F6S2NIS_A')->first();
        $rep = Report::where('student_id', $stA->id)->latest('id')->first();
        $a = UserBaru::where('username', 'f6s2_a')->first();
        $b = UserBaru::where('username', 'f6s2_b')->first();
        $this->actingAs($a)->get('/siswa/laporan/'.$rep->id)->assertStatus(200)->assertSee('Milik A');
        $this->actingAs($b)->get('/siswa/laporan/'.$rep->id)->assertStatus(404);
        $this->actingAs($a)->get('/siswa/laporan/999999')->assertStatus(404);
    }

    public function test_no_edit_delete_status_routes(): void
    {
        $cat = ReportCategory::where('name', 'TEST_Kat_F6S2')->firstOrFail();
        $this->postReport('f6s2_a', $cat->id, 'X');
        $stA = Student::where('nis', 'F6S2NIS_A')->first();
        $rep = Report::where('student_id', $stA->id)->latest('id')->first();
        $a = UserBaru::where('username', 'f6s2_a')->first();
        $this->actingAs($a)->get('/siswa/laporan/'.$rep->id.'/edit')->assertStatus(404);
        $this->actingAs($a)->put('/siswa/laporan/'.$rep->id, ['title' => 'hack'])->assertStatus(405);
        $this->actingAs($a)->delete('/siswa/laporan/'.$rep->id)->assertStatus(405);
        $this->actingAs($a)->post('/siswa/laporan/'.$rep->id.'/status', ['status' => 'selesai'])->assertStatus(404);
        $this->assertEquals('menunggu', $rep->fresh()->status);
    }

    public function test_roles_and_guest(): void
    {
        $admin = UserBaru::where('role', 'admin')->where('status', 'aktif')->first();
        $guru = UserBaru::where('role', 'guru')->where('status', 'aktif')->first();
        $kepsek = UserBaru::where('role', 'kepala_sekolah')->where('status', 'aktif')->first();
        if ($admin) $this->actingAs($admin)->get('/siswa/laporan')->assertStatus(403);
        if ($guru) $this->actingAs($guru)->get('/siswa/laporan')->assertStatus(403);
        if ($kepsek) $this->actingAs($kepsek)->get('/siswa/laporan')->assertStatus(403);
        $this->refreshApplication();
        Storage::fake('local');
        $this->get('/siswa/laporan')->assertRedirect('/login');
        $this->get('/siswa')->assertRedirect('/login');
    }
}




