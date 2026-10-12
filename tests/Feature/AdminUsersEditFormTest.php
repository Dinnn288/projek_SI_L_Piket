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
 * Form Edit Users disamakan dengan form Tambah: NIS/kelas untuk siswa,
 * NIP untuk guru, dinamis per role; data lama terjaga; validasi unik
 * mengecualikan profil sendiri; ganti role tak menyimpan field lawan.
 */
class AdminUsersEditFormTest extends TestCase
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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_UE'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_UE')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_UE', 'status' => 'aktif']);
        $class2 = SchoolClass::where('name', 'TEST_KELAS_UE2')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_UE2', 'status' => 'aktif']);

        $mk = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $admin = $mk('ue_admin', 'Admin UE', 'admin');
        $siswa = $mk('ue_created_s1', 'Siswa UE', 'siswa');
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'UENIS001', 'class_id' => $class->id]);
        }
        $siswa2 = $mk('ue_created_s2', 'Siswa UE 2', 'siswa');
        if (! Student::where('user_id', $siswa2->id)->exists()) {
            Student::create(['user_id' => $siswa2->id, 'nis' => 'UENIS002', 'class_id' => $class->id]);
        }
        $guru = $mk('ue_created_g1', 'Guru UE', 'guru');
        if (! Teacher::where('user_id', $guru->id)->exists()) {
            Teacher::create(['user_id' => $guru->id, 'nip' => 'UENIP001']);
        }
        $guru2 = $mk('ue_created_g2', 'Guru UE 2', 'guru');
        if (! Teacher::where('user_id', $guru2->id)->exists()) {
            Teacher::create(['user_id' => $guru2->id, 'nip' => 'UENIP002']);
        }
        $adm2 = $mk('ue_created_a1', 'Admin UE 2', 'admin');
        $kepsek = $mk('ue_created_k1', 'Kepsek UE', 'kepala_sekolah');

        $this->ids = [
            'admin' => $admin->id, 'siswa' => $siswa->id, 'siswa2' => $siswa2->id,
            'guru' => $guru->id, 'guru2' => $guru2->id, 'adm2' => $adm2->id, 'kepsek' => $kepsek->id,
            'major' => $major->id, 'class' => $class->id, 'class2' => $class2->id,
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                $uids = UserBaru::where('username', 'like', 'ue\_created\_%')->pluck('id');
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
                SchoolClass::whereIn('id', [$this->ids['class'] ?? 0, $this->ids['class2'] ?? 0])->delete();
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

    private function base(UserBaru $u, string $role): array
    {
        return [
            'name' => $u->name, 'username' => $u->username,
            'role' => $role, 'status' => $u->status,
        ];
    }

    public function test_1_edit_siswa_shows_saved_nis_class(): void
    {
        $u = UserBaru::findOrFail($this->ids['siswa']);
        $html = $this->actingAs($this->admin())->get('/admin/users/'.$u->id.'/edit')
            ->assertStatus(200)->getContent();
        $this->assertStringContainsString('data-sl-user-role', $html);
        $this->assertStringContainsString('value="UENIS001"', $html, 'NIS tersimpan harus tampil.');
        $this->assertStringContainsString('value="'.$this->ids['class'].'"', $html, 'Kelas tersimpan harus tampil.');
        $this->assertStringContainsString('name="nis"', $html);
        $this->assertStringContainsString('name="class_id"', $html);
    }

    public function test_2_edit_guru_shows_saved_nip(): void
    {
        $u = UserBaru::findOrFail($this->ids['guru']);
        $html = $this->actingAs($this->admin())->get('/admin/users/'.$u->id.'/edit')
            ->assertStatus(200)->getContent();
        $this->assertStringContainsString('value="UENIP001"', $html, 'NIP tersimpan harus tampil.');
        $this->assertStringContainsString('name="nip"', $html);
    }

    public function test_3_siswa_identity_kept_when_unchanged(): void
    {
        $u = UserBaru::findOrFail($this->ids['siswa']);
        // Edit tanpa ubah NIS/kelas: kirim nilai lama -> tetap terjaga.
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, $this->base($u, 'siswa')
            + ['nis' => 'UENIS001', 'class_id' => $this->ids['class']])
            ->assertRedirect(route('admin.users.index'));
        $this->assertEquals('UENIS001', $u->fresh()->student->nis);
        $this->assertEquals($this->ids['class'], (int) $u->fresh()->student->class_id);
        // Ubah NIS + kelas valid -> tersimpan.
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, $this->base($u, 'siswa')
            + ['nis' => 'UENIS001B', 'class_id' => $this->ids['class2']])
            ->assertRedirect(route('admin.users.index'));
        $this->assertEquals('UENIS001B', $u->fresh()->student->nis);
        $this->assertEquals($this->ids['class2'], (int) $u->fresh()->student->class_id);
    }

    public function test_4_guru_nip_kept_when_unchanged(): void
    {
        $u = UserBaru::findOrFail($this->ids['guru']);
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, $this->base($u, 'guru')
            + ['nip' => 'UENIP001'])
            ->assertRedirect(route('admin.users.index'));
        $this->assertEquals('UENIP001', $u->fresh()->teacher->nip);
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, $this->base($u, 'guru')
            + ['nip' => 'UENIP001B'])
            ->assertRedirect(route('admin.users.index'));
        $this->assertEquals('UENIP001B', $u->fresh()->teacher->nip);
    }

    public function test_5_role_switch_shows_matching_fields(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/users/'.$this->ids['siswa'].'/edit')
            ->assertStatus(200)->getContent();
        $this->assertStringContainsString('data-sl-user-nis-wrap', $html);
        $this->assertStringContainsString('data-sl-user-nip-wrap', $html);
        $this->assertStringContainsString('data-sl-user-class-wrap', $html);
        $this->assertStringContainsString('data-sl-user-role', $html);
    }

    public function test_6_empty_identity_rejected_on_edit(): void
    {
        $u = UserBaru::findOrFail($this->ids['siswa']);
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, $this->base($u, 'siswa')
            + ['class_id' => $this->ids['class']])
            ->assertSessionHasErrors('nis');
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, $this->base($u, 'siswa')
            + ['nis' => 'UENIS001'])
            ->assertSessionHasErrors('class_id');
        $g = UserBaru::findOrFail($this->ids['guru']);
        $this->actingAs($this->admin())->put('/admin/users/'.$g->id, $this->base($g, 'guru'))
            ->assertSessionHasErrors('nip');
    }

    public function test_7_duplicate_identity_rejected_but_own_allowed(): void
    {
        $u = UserBaru::findOrFail($this->ids['siswa']);
        // NIS milik sendiri boleh.
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, $this->base($u, 'siswa')
            + ['nis' => 'UENIS001', 'class_id' => $this->ids['class']])
            ->assertRedirect(route('admin.users.index'));
        // NIS milik siswa lain ditolak.
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, $this->base($u, 'siswa')
            + ['nis' => 'UENIS002', 'class_id' => $this->ids['class']])
            ->assertSessionHasErrors('nis');

        $g = UserBaru::findOrFail($this->ids['guru']);
        $this->actingAs($this->admin())->put('/admin/users/'.$g->id, $this->base($g, 'guru')
            + ['nip' => 'UENIP001'])
            ->assertRedirect(route('admin.users.index'));
        $this->actingAs($this->admin())->put('/admin/users/'.$g->id, $this->base($g, 'guru')
            + ['nip' => 'UENIP002'])
            ->assertSessionHasErrors('nip');
    }

    public function test_8_role_switch_does_not_store_opposite_identity(): void
    {
        // Siswa -> guru: kirim NIS lawan, harus diabaikan; NIP dipakai.
        $u = UserBaru::findOrFail($this->ids['siswa']);
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, $this->base($u, 'guru')
            + ['nis' => 'UENIS001', 'nip' => 'UENIP_SW'])
            ->assertRedirect(route('admin.users.index'));
        $fresh = $u->fresh();
        $this->assertEquals('guru', $fresh->role);
        $this->assertEquals('UENIP_SW', $fresh->teacher->nip);
        $this->assertNull(Student::where('user_id', $fresh->id)->first());
        $this->assertNull(Student::where('nis', 'UENIS001')->first(), 'Profil siswa lama tak-berelasi ikut dipindah, bukan diduplikat.');

        // Guru -> siswa: kirim NIP lawan, harus diabaikan; NIS dipakai.
        $g = UserBaru::findOrFail($this->ids['guru2']);
        $this->actingAs($this->admin())->put('/admin/users/'.$g->id, $this->base($g, 'siswa')
            + ['nip' => 'UENIP002', 'nis' => 'UENIS_SW', 'class_id' => $this->ids['class']])
            ->assertRedirect(route('admin.users.index'));
        $freshG = $g->fresh();
        $this->assertEquals('siswa', $freshG->role);
        $this->assertEquals('UENIS_SW', $freshG->student->nis);
        $this->assertNull(Teacher::where('user_id', $freshG->id)->first());
        $this->assertNull(Teacher::where('nip', 'UENIP002')->first());
    }

    public function test_9_admin_kepsek_edit_unchanged(): void
    {
        $a = UserBaru::findOrFail($this->ids['adm2']);
        $this->actingAs($this->admin())->get('/admin/users/'.$a->id.'/edit')->assertStatus(200);
        $this->actingAs($this->admin())->put('/admin/users/'.$a->id, $this->base($a, 'admin'))
            ->assertRedirect(route('admin.users.index'));
        $this->assertNull($a->fresh()->student);
        $this->assertNull($a->fresh()->teacher);

        $k = UserBaru::findOrFail($this->ids['kepsek']);
        $this->actingAs($this->admin())->put('/admin/users/'.$k->id, $this->base($k, 'kepala_sekolah'))
            ->assertRedirect(route('admin.users.index'));
        $this->assertNull($k->fresh()->student);
        $this->assertNull($k->fresh()->teacher);
    }

    public function test_10_password_optional_username_status_kept(): void
    {
        $u = UserBaru::findOrFail($this->ids['guru']);
        $oldHash = $u->password;
        $this->actingAs($this->admin())->put('/admin/users/'.$u->id, [
            'name' => 'Guru UE Updated', 'username' => $u->username,
            'password' => '', 'password_confirmation' => '',
            'role' => 'guru', 'status' => 'nonaktif', 'nip' => 'UENIP001',
        ])->assertRedirect(route('admin.users.index'));
        $fresh = $u->fresh();
        $this->assertEquals($oldHash, $fresh->password);
        $this->assertEquals('Guru UE Updated', $fresh->name);
        $this->assertEquals('nonaktif', $fresh->status);

        $this->refreshApplication();
        $this->reconnect();
        // Nonaktif -> login ditolak; aktifkan kembali -> login guru OK.
        $this->post('/login', ['username' => $u->username, 'password' => 'password123']);
        $this->assertGuest();
        $admin = UserBaru::where('username', 'ue_admin')->firstOrFail();
        $this->actingAs($admin)->put('/admin/users/'.$u->id, [
            'name' => 'Guru UE Updated', 'username' => $u->username,
            'role' => 'guru', 'status' => 'aktif', 'nip' => 'UENIP001',
        ])->assertRedirect(route('admin.users.index'));
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => $u->username, 'password' => 'password123'])->assertRedirect('/guru');
    }
}
