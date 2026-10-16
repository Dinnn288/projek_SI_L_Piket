<?php

namespace Tests\Feature;

use App\Models\DutySchedule;
use App\Models\Major;
use App\Models\ReportCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Services\GuruDutyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SidebarConsistencyTest extends TestCase
{
    private function reconnect(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket_test']);
        DB::reconnect('mysql');
        \Tests\TestDatabaseGuard::assertTestDatabaseSafe();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Carbon::setTestNow(Carbon::today()->setTime(9, 0));

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_SBC'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_SBC')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_SBC', 'status' => 'aktif']);
        ReportCategory::firstOrCreate(['name' => 'TEST_Kat_SBC'], ['description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $guru = $mkUser('sbc_guru', 'Guru SBC', 'guru');
        $siswa = $mkUser('sbc_siswa', 'Siswa SBC', 'siswa');
        if (! Teacher::where('user_id', $guru->id)->exists()) {
            Teacher::create(['user_id' => $guru->id, 'nip' => 'SBCNIP001']);
        }
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'SBCNIS001', 'class_id' => $class->id]);
        }
        $today = GuruDutyService::todayDay() ?? 'senin';
        $teacher = Teacher::where('user_id', $guru->id)->firstOrFail();
        if (! DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
            DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            $sids = Student::where('nis', 'SBCNIS001')->pluck('id');
            $rids = \App\Models\Report::whereIn('student_id', $sids)->pluck('id');
            if ($rids->isNotEmpty()) {
                \App\Models\Notification::whereIn('report_id', $rids)->delete();
                \App\Models\ReportHistory::whereIn('report_id', $rids)->delete();
                \App\Models\ReportAttachment::whereIn('report_id', $rids)->delete();
                \App\Models\Report::whereIn('id', $rids)->delete();
            }
            Student::where('nis', 'SBCNIS001')->delete();
            $gids = UserBaru::whereIn('username', ['sbc_guru', 'sbc_siswa'])->pluck('id');
            $tids = Teacher::whereIn('user_id', $gids)->pluck('id');
            if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->delete();
            Teacher::whereIn('user_id', $gids)->delete();
            UserBaru::whereIn('username', ['sbc_guru', 'sbc_siswa'])->delete();
            ReportCategory::where('name', 'TEST_Kat_SBC')->delete();
            SchoolClass::where('name', 'TEST_KELAS_SBC')->delete();
            Major::where('name', 'TEST_MAJOR_SBC')->delete();
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function sidebarMeta(string $html): array
    {
        $nip = '-';
        $day = '-';
        $class = '-';
        if (preg_match('/NIP:\s*([^|<]+?)\s*\|\s*Hari ini:\s*([^<]+)/u', $html, $m)) {
            $nip = trim(strip_tags($m[1]));
            $day = trim(strip_tags($m[2]));
        }
        if (preg_match('/<div class="meta">([^<]+)<\/div>/u', $html, $m2)) {
            $class = trim(strip_tags($m2[1]));
        }

        return [$nip, $day, $class];
    }

    public function test_guru_sidebar_consistent(): void
    {
        $guru = UserBaru::where('username', 'sbc_guru')->firstOrFail();
        $expectedNip = Teacher::where('user_id', $guru->id)->value('nip');
        $expectedDay = GuruDutyService::todayDay();
        $results = [];
        foreach (['/guru', '/guru/laporan', '/guru/notifikasi', '/guru/profil'] as $url) {
            $res = $this->actingAs($guru)->get($url);
            $res->assertStatus(200);
            [$nip, $day] = $this->sidebarMeta($res->getContent());
            $results[$url] = [$nip, $day];
            $this->assertEquals($expectedNip, $nip, "NIP mismatch at $url");
            $this->assertEquals($expectedDay, $day, "Day mismatch at $url");
            $this->assertNotEquals('-', $nip, "NIP fallback at $url");
        }
        // Multi-user: guru lain tidak melihat NIP guru ini
        $guru2 = UserBaru::create(['name' => 'Guru SBC2', 'username' => 'sbc_guru2', 'password' => Hash::make('x'), 'role' => 'guru', 'status' => 'aktif']);
        Teacher::create(['user_id' => $guru2->id, 'nip' => 'SBCNIP002']);
        $res2 = $this->actingAs($guru2)->get('/guru/notifikasi');
        $res2->assertStatus(200);
        $this->assertStringNotContainsString($expectedNip, $res2->getContent());
        Teacher::where('user_id', $guru2->id)->delete();
        $guru2->delete();
    }

    public function test_siswa_sidebar_consistent(): void
    {
        $siswa = UserBaru::where('username', 'sbc_siswa')->firstOrFail();
        $expectedClass = 'TEST_KELAS_SBC';
        foreach (['/siswa', '/siswa/laporan', '/siswa/laporan/create', '/siswa/notifikasi', '/siswa/profil'] as $url) {
            $res = $this->actingAs($siswa)->get($url);
            $res->assertStatus(200);
            [, , $meta] = $this->sidebarMeta($res->getContent());
            $this->assertStringContainsString($expectedClass, $meta, "Class mismatch at $url");
        }
        // Multi-user: siswa lain tidak melihat kelas/metanya
        $siswa2 = UserBaru::create(['name' => 'Siswa SBC2', 'username' => 'sbc_siswa2', 'password' => Hash::make('x'), 'role' => 'siswa', 'status' => 'aktif']);
        $res2 = $this->actingAs($siswa2)->get('/siswa/notifikasi');
        // Tanpa record student: 403 (proteksi existing), bukan data orang lain
        $this->assertContains($res2->getStatusCode(), [200, 403, 404]);
        if ($res2->getStatusCode() === 200) {
            $this->assertStringNotContainsString($expectedClass.' |', $res2->getContent());
        }
        $siswa2->delete();
    }
}
