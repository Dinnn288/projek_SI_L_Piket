<?php

namespace Tests\Feature;

use App\Models\DutySchedule;
use App\Models\Major;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportHistory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Support\HandlesNipUniqueViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * B2.2-H2 — handler pelanggaran UNIQUE teachers.nip.
 *
 * BELUM DIJALANKAN (menunggu guard + persetujuan): sebagian skenario
 * membutuhkan indeks `teachers_nip_unique` yang belum ada (migration
 * terpisah). Test unit identifikasi exception (tanpa DB) aman kapan pun;
 * test HTTP ditandai membutuhkan migration.
 *
 * ISOLASI (saat dijalankan): pola useTestDb + assert nama DB, mengikuti
 * ReportIdempotencyTest/AdminUsersDeleteTest/IdentityTrimResolveTest.
 */
class NipUniqueHandlerTest extends TestCase
{
    use HandlesNipUniqueViolation;

    private array $ids = [];

    private function useTestDb(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket_test']);
        DB::reconnect('mysql');
        $this->assertEquals('si_l_piket_test', DB::connection()->getDatabaseName());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->useTestDb();
        Cache::flush();

        $major = Major::create(['name' => 'TEST_MAJOR_NIP', 'status' => 'aktif']);
        $class = SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_NIP', 'status' => 'aktif']);
        $admin = UserBaru::create(['name' => 'Admin NIP', 'username' => 'nip_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);

        $this->ids = ['admin' => $admin->id, 'major' => $major->id, 'class' => $class->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->useTestDb();
            if (! empty($this->ids)) {
                $uids = UserBaru::where('username', 'like', 'nip\_%')->pluck('id');
                if ($uids->isNotEmpty()) {
                    $sids = Student::whereIn('user_id', $uids)->pluck('id');
                    if ($sids->isNotEmpty()) {
                        $rids = Report::whereIn('student_id', $sids)->pluck('id');
                        if ($rids->isNotEmpty()) {
                            Notification::whereIn('report_id', $rids)->delete();
                            ReportHistory::whereIn('report_id', $rids)->delete();
                            ReportAttachment::whereIn('report_id', $rids)->delete();
                            Report::whereIn('id', $rids)->delete();
                        }
                        Student::whereIn('id', $sids)->delete();
                    }
                    $tids = Teacher::whereIn('user_id', $uids)->pluck('id');
                    if ($tids->isNotEmpty()) {
                        DutySchedule::whereIn('teacher_id', $tids)->delete();
                    }
                    Teacher::whereIn('user_id', $uids)->delete();
                    Notification::whereIn('user_id', $uids)->delete();
                    ReportHistory::whereIn('user_id', $uids)->delete();
                    UserBaru::whereIn('id', $uids)->delete();
                }
                UserBaru::whereIn('id', [$this->ids['admin'] ?? 0])->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
            Cache::flush();
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function duplicateException(string $index = 'teachers_nip_unique'): QueryException
    {
        // Mock realistis MySQL: PDOException previous membawa SQLSTATE di
        // getCode() + errorInfo [SQLSTATE, driver code, pesan]. QueryException
        // Laravel menyalin errorInfo dari previous (bukan dari bindings []),
        // persis seperti exception produksi dari driver PDO.
        $message = "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'X' for key '$index'";
        $previous = new \PDOException($message, '23000');
        $previous->errorInfo = ['23000', 1062, $message];

        return new QueryException('mysql', 'insert into `teachers` (...) values (...)', [], $previous);
    }

    public function test_1_identifies_nip_violation(): void
    {
        $this->assertTrue(static::isNipUniqueViolation($this->duplicateException('teachers_nip_unique')));
    }

    public function test_2_other_index_not_misreported(): void
    {
        // Constraint lain (username/NIS/FK) TIDAK boleh menjadi error NIP.
        $this->assertFalse(static::isNipUniqueViolation($this->duplicateException('users_baru_username_unique')));
        $this->assertFalse(static::isNipUniqueViolation($this->duplicateException('students_nis_unique')));
        $fkPrevious = new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row', '23000');
        $fkPrevious->errorInfo = ['23000', 1451, 'Cannot delete or update a parent row (a foreign key constraint fails)'];
        $fk = new QueryException('mysql', 'delete from ...', [], $fkPrevious);
        $this->assertFalse(static::isNipUniqueViolation($fk));
        $this->assertFalse(static::isNipUniqueViolation(new \RuntimeException('bukan query exception')));
    }

    public function test_3_app_validation_rejects_duplicate_nip(): void
    {
        $admin = UserBaru::findOrFail($this->ids['admin']);
        $this->actingAs($admin)->post('/admin/teachers', [
            'name' => 'Guru NIP', 'username' => 'nip_t_g1', 'password' => 'password123',
            'password_confirmation' => 'password123', 'status' => 'aktif', 'nip' => 'NIPDUP001',
        ])->assertRedirect(route('admin.teachers.index'));
        $this->actingAs($admin)->post('/admin/teachers', [
            'name' => 'Guru NIP 2', 'username' => 'nip_t_g2', 'password' => 'password123',
            'password_confirmation' => 'password123', 'status' => 'aktif', 'nip' => 'NIPDUP001',
        ])->assertSessionHasErrors('nip');
    }

    /**
     * Membutuhkan indeks teachers_nip_unique aktif (tahap M3C/M3D).
     * Melewati validasi aplikasi dengan NIP yang lolos Rule::unique lalu
     * diduplikatkan sebelum INSERT profil — mensimulasikan race check-then-
     * insert tanpa thread paralel.
     *
     * @group needs-unique-migration
     */
    public function test_4_db_violation_becomes_nip_error(): void
    {
        $this->requireUniqueIndex();
        $admin = UserBaru::findOrFail($this->ids['admin']);
        // Pre-seed NIP langsung via DB (bypass validasi aplikasi).
        $seed = UserBaru::create(['name' => 'Seed', 'username' => 'nip_t_seed4', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        Teacher::create(['user_id' => $seed->id, 'nip' => 'NIPRACE001']);
        // Validasi aplikasi melihat NIP bebas (belum ada saat validate),
        // tetapi INSERT profil menabrak UNIQUE -> handler 422 nip.
        // Simulasi: buat request dengan NIP yang baru saja dipakai proses
        // lain — validasi Rule::unique berjalan SEBELUM insert pesaing.
        // Di sini pesaing sudah lebih dulu (seed), sehingga jalur yang
        // diuji adalah validasi aplikasi; jalur handler diuji test_4b.
        $this->actingAs($admin)->post('/admin/teachers', [
            'name' => 'Guru Race', 'username' => 'nip_t_g4', 'password' => 'password123',
            'password_confirmation' => 'password123', 'status' => 'aktif', 'nip' => 'NIPRACE001',
        ])->assertSessionHasErrors('nip');
    }

    public function test_4b_handler_converts_real_db_violation(): void
    {
        // Membutuhkan indeks aktif. Bypass validasi aplikasi (seolah race
        // lolos check-then-insert), panggil helper transaksi controller
        // langsung dengan NIP duplikat -> UNIQUE DB melempar 1062 ->
        // handler konversi menjadi error validasi nip (bukan 500).
        $this->requireUniqueIndex();
        $seed = UserBaru::create(['name' => 'Seed', 'username' => 'nip_t_seed4b', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        Teacher::create(['user_id' => $seed->id, 'nip' => 'NIPHDL001']);

        $controller = new \App\Http\Controllers\Admin\TeacherController();
        $ref = new \ReflectionMethod($controller, 'transactionalNipSafe');
        $caught = null;
        try {
            $ref->invoke($controller, function () {
                $u = UserBaru::create(['name' => 'Race', 'username' => 'nip_t_race4b', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
                // UNIQUE DB menolak di sini (race window).
                Teacher::create(['user_id' => $u->id, 'nip' => 'NIPHDL001']);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught, 'Handler harus konversi pelanggaran menjadi error validasi.');
        $this->assertArrayHasKey('nip', $caught->errors());
        $this->assertEquals('NIP sudah digunakan.', $caught->errors()['nip'][0]);
        $this->assertNull(UserBaru::where('username', 'nip_t_race4b')->first(), 'Rollback: akun parsial tidak tersisa.');
        $this->assertEquals(1, Teacher::where('nip', 'NIPHDL001')->count());
    }

    /**
     * @group needs-unique-migration
     */
    public function test_5_json_gets_422_structure(): void
    {
        $this->requireUniqueIndex();
        $admin = UserBaru::findOrFail($this->ids['admin']);
        $this->actingAs($admin)->post('/admin/teachers', [
            'name' => 'Guru JSON', 'username' => 'nip_t_g5', 'password' => 'password123',
            'password_confirmation' => 'password123', 'status' => 'aktif', 'nip' => 'NIPJSON001',
        ])->assertRedirect(route('admin.teachers.index'));
        $res = $this->actingAs($admin)->postJson('/admin/teachers', [
            'name' => 'Guru JSON 2', 'username' => 'nip_t_g5b', 'password' => 'password123',
            'password_confirmation' => 'password123', 'status' => 'aktif', 'nip' => 'NIPJSON001',
        ]);
        $res->assertStatus(422);
        $res->assertJsonValidationErrors('nip');
        $this->assertStringNotContainsString('SQLSTATE', $res->getContent(), 'SQL mentah tidak boleh bocor.');
    }

    /**
     * @group needs-unique-migration
     */
    public function test_6_no_partial_persist_and_relations_kept(): void
    {
        $this->requireUniqueIndex();
        $admin = UserBaru::findOrFail($this->ids['admin']);
        // Guru existing + jadwal + histori simulasi via laporan? Minimal:
        // duplikat NIP pada update tidak mengubah data lama.
        $u = UserBaru::create(['name' => 'Guru Keep', 'username' => 'nip_t_keep', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        $t = Teacher::create(['user_id' => $u->id, 'nip' => 'NIPKEEP001']);
        $sched = \App\Models\DutySchedule::create(['teacher_id' => $t->id, 'day' => 'senin']);
        // NIP pesaing.
        $other = UserBaru::create(['name' => 'Guru Other', 'username' => 'nip_t_other', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        Teacher::create(['user_id' => $other->id, 'nip' => 'NIPOTHER001']);
        // Update ke NIP milik orang lain -> ditolak, data lama utuh.
        $this->actingAs($admin)->put('/admin/teachers/'.$t->id, [
            'name' => 'Guru Keep', 'username' => 'nip_t_keep', 'status' => 'aktif', 'nip' => 'NIPOTHER001',
        ])->assertSessionHasErrors('nip');
        $this->assertEquals('NIPKEEP001', $t->fresh()->nip);
        $this->assertEquals($u->id, $t->fresh()->user_id);
        $this->assertNotNull(\App\Models\DutySchedule::find($sched->id), 'Jadwal tetap utuh.');
    }

    /**
     * @group needs-unique-migration
     */
    public function test_7_concurrent_duplicate_race(): void
    {
        // Tanpa thread paralel deterministik di PHP: dua INSERT berurutan
        // dengan NIP sama — yang kedua HARUS gagal 1062. Ini membuktikan
        // constraint menutup race yang validasi aplikasi tidak bisa.
        $this->requireUniqueIndex();
        $a = UserBaru::create(['name' => 'Race A', 'username' => 'nip_t_racea', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        $b = UserBaru::create(['name' => 'Race B', 'username' => 'nip_t_raceb', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        Teacher::create(['user_id' => $a->id, 'nip' => 'NIPRACEQ01']);
        $failed = false;
        try {
            Teacher::create(['user_id' => $b->id, 'nip' => 'NIPRACEQ01']);
        } catch (\Illuminate\Database\QueryException $e) {
            $failed = true;
            $this->assertTrue((new class
            {
                use \App\Support\HandlesNipUniqueViolation;
            })::isNipUniqueViolation($e));
        }
        $this->assertTrue($failed, 'INSERT kedua harus ditolak UNIQUE.');
        $this->assertEquals(1, Teacher::where('nip', 'NIPRACEQ01')->count());
    }

    private function requireUniqueIndex(): void
    {
        $has = DB::selectOne(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers' AND INDEX_NAME = 'teachers_nip_unique' AND COLUMN_NAME = 'nip'"
        );
        if (! $has) {
            $this->markTestSkipped('Indeks teachers_nip_unique belum aktif pada database test.');
        }
    }
}
