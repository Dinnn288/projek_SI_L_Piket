<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportHistory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Form Users terpadu: satu formulir dengan NIS (siswa) / NIP (guru)
 * dinamis per role, memakai aturan validasi yang sama dengan form
 * Siswa/Guru (NIS wajib+unik, NIP wajib+unik, kelas wajib siswa).
 */
class AdminUsersUnifiedFormTest extends TestCase
{
    private array $ids = [];

    private function reconnect(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket']);
        DB::reconnect('mysql');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_UF'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_UF')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_UF', 'status' => 'aktif']);

        $admin = UserBaru::where('username', 'uf_admin')->first()
            ?: UserBaru::create(['name' => 'Admin UF', 'username' => 'uf_admin', 'password' => Hash::make('password123'), 'role' => 'admin', 'status' => 'aktif']);

        $this->ids = ['admin' => $admin->id, 'major' => $major->id, 'class' => $class->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $uids = UserBaru::where('username', 'like', 'uf\_created\_%')->pluck('id');
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
                    Teacher::whereIn('user_id', $uids)->delete();
                    UserBaru::whereIn('id', $uids)->delete();
                }
                UserBaru::whereIn('id', [$this->ids['admin'] ?? 0])->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function admin(): UserBaru
    {
        return UserBaru::findOrFail($this->ids['admin']);
    }

    private function base(string $username, string $role): array
    {
        return [
            'name' => 'Nama '.$username, 'username' => $username,
            'password' => 'password123', 'password_confirmation' => 'password123',
            'role' => $role, 'status' => 'aktif',
        ];
    }

    public function test_form_renders_dynamic_identity_fields(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/users/create')
            ->assertStatus(200)->getContent();
        // Satu form, role switch, NIS/NIP/kelas dinamis + JS sinkron.
        $this->assertStringContainsString('data-sl-user-role', $html);
        $this->assertStringContainsString('name="nis"', $html);
        $this->assertStringContainsString('name="nip"', $html);
        $this->assertStringContainsString('name="class_id"', $html);
        $this->assertEquals(1, substr_count($html, 'name="nis"'), 'NIS tepat satu field.');
        $this->assertEquals(1, substr_count($html, 'name="nip"'), 'NIP tepat satu field.');
        $this->assertStringContainsString("action=\"".route('admin.users.store').'"', $html);
    }

    public function test_1_create_siswa_valid(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_s1', 'siswa')
            + ['nis' => 'UFNIS001', 'class_id' => $this->ids['class']])
            ->assertRedirect(route('admin.users.index'));
        $u = UserBaru::where('username', 'uf_created_s1')->firstOrFail();
        $this->assertEquals('siswa', $u->role);
        $this->assertEquals('UFNIS001', $u->student->nis);
        $this->assertEquals($this->ids['class'], (int) $u->student->class_id);
        $this->assertNull($u->teacher);
    }

    public function test_2_create_guru_valid(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_g1', 'guru')
            + ['nip' => 'UFNIP001'])
            ->assertRedirect(route('admin.users.index'));
        $u = UserBaru::where('username', 'uf_created_g1')->firstOrFail();
        $this->assertEquals('guru', $u->role);
        $this->assertEquals('UFNIP001', $u->teacher->nip);
        $this->assertNull($u->student);
    }

    public function test_3_4_role_switch_clears_identity_server_side(): void
    {
        // Payload kirim NIS saat role guru -> NIS diabaikan, NIP dipakai.
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_sw1', 'guru')
            + ['nis' => 'UFNIS_SW', 'nip' => 'UFNIP_SW'])
            ->assertRedirect(route('admin.users.index'));
        $u = UserBaru::where('username', 'uf_created_sw1')->firstOrFail();
        $this->assertEquals('UFNIP_SW', $u->teacher->nip);
        $this->assertNull(Student::where('nis', 'UFNIS_SW')->first(), 'NIS tidak boleh tersimpan saat role guru.');

        // Payload kirim NIP saat role siswa -> NIP diabaikan, NIS dipakai.
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_sw2', 'siswa')
            + ['nip' => 'UFNIP_SW2', 'nis' => 'UFNIS_SW2', 'class_id' => $this->ids['class']])
            ->assertRedirect(route('admin.users.index'));
        $u2 = UserBaru::where('username', 'uf_created_sw2')->firstOrFail();
        $this->assertEquals('UFNIS_SW2', $u2->student->nis);
        $this->assertNull(Teacher::where('nip', 'UFNIP_SW2')->first(), 'NIP tidak boleh tersimpan saat role siswa.');
    }

    public function test_5_empty_identity_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_e1', 'siswa')
            + ['class_id' => $this->ids['class']])
            ->assertSessionHasErrors('nis');
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_e2', 'siswa')
            + ['nis' => 'UFNIS_E2'])
            ->assertSessionHasErrors('class_id');
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_e3', 'guru'))
            ->assertSessionHasErrors('nip');
        $this->assertNull(UserBaru::where('username', 'uf_created_e1')->first());
        $this->assertNull(UserBaru::where('username', 'uf_created_e2')->first());
        $this->assertNull(UserBaru::where('username', 'uf_created_e3')->first());
    }

    public function test_6_duplicate_identity_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_d1', 'siswa')
            + ['nis' => 'UFNIS_DUP', 'class_id' => $this->ids['class']])
            ->assertRedirect(route('admin.users.index'));
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_d2', 'siswa')
            + ['nis' => 'UFNIS_DUP', 'class_id' => $this->ids['class']])
            ->assertSessionHasErrors('nis');
        $this->assertNull(UserBaru::where('username', 'uf_created_d2')->first());

        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_d3', 'guru')
            + ['nip' => 'UFNIP_DUP'])
            ->assertRedirect(route('admin.users.index'));
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_d4', 'guru')
            + ['nip' => 'UFNIP_DUP'])
            ->assertSessionHasErrors('nip');
        $this->assertNull(UserBaru::where('username', 'uf_created_d4')->first());
    }

    public function test_7_duplicate_username_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_u1', 'guru')
            + ['nip' => 'UFNIP_U1'])
            ->assertRedirect(route('admin.users.index'));
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_u1', 'siswa')
            + ['nis' => 'UFNIS_U1', 'class_id' => $this->ids['class']])
            ->assertSessionHasErrors('username');
    }

    public function test_8_new_accounts_can_login(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_l1', 'siswa')
            + ['nis' => 'UFNIS_L1', 'class_id' => $this->ids['class']])
            ->assertRedirect(route('admin.users.index'));
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_l2', 'guru')
            + ['nip' => 'UFNIP_L2'])
            ->assertRedirect(route('admin.users.index'));

        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'uf_created_l1', 'password' => 'password123'])->assertRedirect('/siswa');

        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'uf_created_l2', 'password' => 'password123'])->assertRedirect('/guru');
    }

    public function test_9_admin_kepsek_unchanged(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_a1', 'admin'))
            ->assertRedirect(route('admin.users.index'));
        $this->actingAs($this->admin())->post('/admin/users', $this->base('uf_created_k1', 'kepala_sekolah'))
            ->assertRedirect(route('admin.users.index'));
        $a = UserBaru::where('username', 'uf_created_a1')->firstOrFail();
        $k = UserBaru::where('username', 'uf_created_k1')->firstOrFail();
        $this->assertNull($a->student);
        $this->assertNull($a->teacher);
        $this->assertNull($k->student);
        $this->assertNull($k->teacher);
    }

    public function test_10_teacher_form_nip_unique(): void
    {
        $this->actingAs($this->admin())->post('/admin/teachers', [
            'name' => 'Guru UF', 'username' => 'uf_created_t1', 'password' => 'password123',
            'password_confirmation' => 'password123', 'status' => 'aktif', 'nip' => 'UFNIP_T1',
        ])->assertRedirect(route('admin.teachers.index'));
        $this->actingAs($this->admin())->post('/admin/teachers', [
            'name' => 'Guru UF 2', 'username' => 'uf_created_t2', 'password' => 'password123',
            'password_confirmation' => 'password123', 'status' => 'aktif', 'nip' => 'UFNIP_T1',
        ])->assertSessionHasErrors('nip');
    }
}
