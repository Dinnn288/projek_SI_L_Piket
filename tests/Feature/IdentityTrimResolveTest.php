<?php

namespace Tests\Feature;

use App\Exceptions\MissingTeacherProfile;
use App\Models\Major;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * B2.1 — trim identitas + resolveForUser tanpa NIP palsu.
 *
 * ISOLASI: memakai database `si_l_piket_test` (bukan produksi).
 */
class IdentityTrimResolveTest extends TestCase
{
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

        $major = Major::create(['name' => 'TEST_MAJOR_TR', 'status' => 'aktif']);
        $class = SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_TR', 'status' => 'aktif']);
        $admin = UserBaru::create(['name' => 'Admin TR', 'username' => 'tr_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);

        $this->ids = ['admin' => $admin->id, 'major' => $major->id, 'class' => $class->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->useTestDb();
            if (! empty($this->ids)) {
                $uids = UserBaru::where('username', 'like', 'tr\_created\_%')->pluck('id');
                if ($uids->isNotEmpty()) {
                    Student::whereIn('user_id', $uids)->delete();
                    Teacher::whereIn('user_id', $uids)->delete();
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

    private function admin(): UserBaru
    {
        return UserBaru::findOrFail($this->ids['admin']);
    }

    public function test_1_username_trimmed_on_create(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Guru Spasi', 'username' => '  tr_created_g1  ',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'guru', 'status' => 'aktif', 'nip' => 'TRNIP001',
        ])->assertRedirect(route('admin.users.index'));
        $u = UserBaru::where('username', 'tr_created_g1')->first();
        $this->assertNotNull($u, 'Username harus tersimpan tanpa spasi tepi.');
        $this->assertNull(UserBaru::where('username', '  tr_created_g1  ')->first());
    }

    public function test_2_login_with_padded_username(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Siswa Spasi', 'username' => 'tr_created_s1',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'siswa', 'status' => 'aktif', 'nis' => 'TRNIS001', 'class_id' => $this->ids['class'],
        ])->assertRedirect(route('admin.users.index'));

        $this->refreshApplication();
        $this->useTestDb();
        // Login dengan spasi tepi mengikuti kebijakan trim yang sama.
        $this->post('/login', ['username' => '  tr_created_s1  ', 'password' => 'password123'])->assertRedirect('/siswa');
    }

    public function test_3_nis_nip_trimmed(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Siswa NIS', 'username' => 'tr_created_s2',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'siswa', 'status' => 'aktif', 'nis' => '  TRNIS002  ', 'class_id' => $this->ids['class'],
        ])->assertRedirect(route('admin.users.index'));
        $this->assertEquals('TRNIS002', UserBaru::where('username', 'tr_created_s2')->firstOrFail()->student->nis);

        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Guru NIP', 'username' => 'tr_created_g2',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'guru', 'status' => 'aktif', 'nip' => '  TRNIP002  ',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertEquals('TRNIP002', UserBaru::where('username', 'tr_created_g2')->firstOrFail()->teacher->nip);
    }

    public function test_5_6_duplicates_rejected_own_allowed(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'A', 'username' => 'tr_created_d1',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'guru', 'status' => 'aktif', 'nip' => 'TRNIP_D',
        ])->assertRedirect(route('admin.users.index'));
        // Duplikat dengan spasi tepi tetap terdeteksi setelah trim.
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'B', 'username' => '  tr_created_d1  ',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'guru', 'status' => 'aktif', 'nip' => 'TRNIP_D2',
        ])->assertSessionHasErrors('username');
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'C', 'username' => 'tr_created_d3',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'guru', 'status' => 'aktif', 'nip' => '  TRNIP_D  ',
        ])->assertSessionHasErrors('nip');

        // Update milik sendiri lolos.
        $u = UserBaru::where('username', 'tr_created_d1')->firstOrFail();
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, [
            'name' => 'A', 'username' => 'tr_created_d1',
            'role' => 'guru', 'status' => 'aktif', 'nip' => 'TRNIP_D',
        ])->assertRedirect(route('admin.users.index'));
    }

    public function test_7_8_resolve_keeps_nip_never_from_username(): void
    {
        $u = UserBaru::create(['name' => 'Guru TR', 'username' => 'tr_created_g3', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        Teacher::create(['user_id' => $u->id, 'nip' => 'TRNIP_KEEP']);
        // Profil ada: NIP tidak berubah walau dipanggil berulang.
        $this->assertEquals('TRNIP_KEEP', Teacher::resolveForUser($u->fresh())->nip);
        $this->assertEquals('TRNIP_KEEP', Teacher::resolveForUser($u->fresh())->nip);
        $this->assertEquals('TRNIP_KEEP', $u->fresh()->teacher->nip);

        // Profil belum ada: TIDAK membuat NIP dari username.
        $bare = UserBaru::create(['name' => 'Guru Bare', 'username' => 'tr_created_g4', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        try {
            Teacher::resolveForUser($bare->fresh());
            $this->fail('Harus melempar MissingTeacherProfile.');
        } catch (MissingTeacherProfile $e) {
            $this->assertStringContainsString('tr_created_g4', $e->getMessage());
        }
        $this->assertNull(Teacher::where('user_id', $bare->id)->first(), 'Tidak boleh ada profil NIP-username.');
        $this->assertNull(Teacher::where('nip', 'tr_created_g4')->first());
    }

    public function test_9_10_orphan_handled_and_callers_safe(): void
    {
        $bare = UserBaru::create(['name' => 'Guru Orphan', 'username' => 'tr_created_g5', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        // Controller guru: 403 terkendali, bukan 500.
        $this->actingAs($bare)->get('/guru')->assertStatus(403);
        $this->actingAs($bare)->get('/guru/laporan')->assertStatus(403);

        // Guru berprofil tetap 200.
        $ok = UserBaru::create(['name' => 'Guru OK', 'username' => 'tr_created_g6', 'password' => Hash::make('password123'), 'role' => 'guru', 'status' => 'aktif']);
        Teacher::create(['user_id' => $ok->id, 'nip' => 'TRNIP_OK']);
        $this->actingAs($ok->fresh())->get('/guru')->assertStatus(200);
    }

    public function test_11_cross_role_and_login_regression(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Siswa RG', 'username' => 'tr_created_r1',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'siswa', 'status' => 'aktif', 'nis' => 'TRNIS_R1', 'class_id' => $this->ids['class'],
        ])->assertRedirect(route('admin.users.index'));
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Admin RG', 'username' => 'tr_created_r2',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => 'admin', 'status' => 'aktif',
        ])->assertRedirect(route('admin.users.index'));

        $this->refreshApplication();
        $this->useTestDb();
        $this->post('/login', ['username' => 'tr_created_r1', 'password' => 'password123'])->assertRedirect('/siswa');
        $this->refreshApplication();
        $this->useTestDb();
        $this->post('/login', ['username' => 'tr_created_r2', 'password' => 'password123'])->assertRedirect('/admin');
    }
}
