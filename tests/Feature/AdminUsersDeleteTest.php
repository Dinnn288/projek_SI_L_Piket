<?php

namespace Tests\Feature;

use App\Models\DutySchedule;
use App\Models\Major;
use App\Models\Notification;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportCategory;
use App\Models\ReportHistory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Hapus akun non-admin oleh admin (isolasi `si_l_piket_test`).
 *
 * Aturan: siswa/guru/kepala_sekolah aktif/nonaktif dapat dihapus bila
 * relasinya aman; jadwal milik guru ikut hapus; laporan+histori
 * dipertahankan (relasi pemblokir menolak hapus); SEMUA akun role admin
 * ditolak dihapus; akun sendiri dilindungi; non-admin 403.
 */
class AdminUsersDeleteTest extends TestCase
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

        $major = Major::create(['name' => 'TEST_MAJOR_DEL', 'status' => 'aktif']);
        $class = SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_DEL', 'status' => 'aktif']);
        $cat = ReportCategory::create(['name' => 'TEST_Kat_DEL', 'description' => 't', 'status' => 'aktif', 'priority' => 'tinggi']);

        $mk = fn (string $u, string $role, string $status = 'aktif') => UserBaru::create(['name' => "Nama $u", 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => $status]);
        $admin = $mk('del_admin', 'admin');
        $admin2 = $mk('del_admin2', 'admin');

        $this->ids = ['admin' => $admin->id, 'admin2' => $admin2->id, 'major' => $major->id, 'class' => $class->id, 'cat' => $cat->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->useTestDb();
            if (! empty($this->ids)) {
                $uids = UserBaru::where('username', 'like', 'del\_target\_%')->pluck('id');
                if ($uids->isNotEmpty()) {
                    $sids = Student::whereIn('user_id', $uids)->pluck('id');
                    $rids = Report::whereIn('student_id', $sids)->pluck('id');
                    if ($rids->isNotEmpty()) {
                        Notification::whereIn('report_id', $rids)->delete();
                        ReportHistory::whereIn('report_id', $rids)->delete();
                        ReportAttachment::whereIn('report_id', $rids)->delete();
                        Report::whereIn('id', $rids)->delete();
                    }
                    Student::whereIn('id', $sids)->delete();
                    $tids = Teacher::whereIn('user_id', $uids)->pluck('id');
                    if ($tids->isNotEmpty()) DutySchedule::whereIn('teacher_id', $tids)->delete();
                    Teacher::whereIn('user_id', $uids)->delete();
                    Notification::whereIn('user_id', $uids)->delete();
                    ReportHistory::whereIn('user_id', $uids)->delete();
                    UserBaru::whereIn('id', $uids)->delete();
                }
                UserBaru::whereIn('id', [$this->ids['admin'] ?? 0, $this->ids['admin2'] ?? 0])->delete();
                ReportCategory::whereKey($this->ids['cat'] ?? 0)->delete();
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

    private function mkTarget(string $suffix, string $role, string $status = 'aktif'): UserBaru
    {
        return UserBaru::create(['name' => "Target $suffix", 'username' => "del_target_$suffix", 'password' => Hash::make('password123'), 'role' => $role, 'status' => $status]);
    }

    public function test_1_delete_active_siswa_without_relations(): void
    {
        $u = $this->mkTarget('s1', 'siswa');
        Student::create(['user_id' => $u->id, 'nis' => 'DELNIS001', 'class_id' => $this->ids['class']]);
        $this->actingAs($this->admin())->delete('/admin/users/'.$u->id)->assertRedirect(route('admin.users.index'));
        $this->assertNull(UserBaru::find($u->id));
        $this->assertNull(Student::where('user_id', $u->id)->first());
    }

    public function test_2_delete_active_guru_with_schedule(): void
    {
        $u = $this->mkTarget('g1', 'guru');
        $t = Teacher::create(['user_id' => $u->id, 'nip' => 'DELNIP001']);
        DutySchedule::create(['teacher_id' => $t->id, 'day' => 'senin']);
        // Jadwal guru lain tidak boleh ikut terhapus.
        $other = $this->mkTarget('g1b', 'guru');
        $tOther = Teacher::create(['user_id' => $other->id, 'nip' => 'DELNIP001B']);
        $otherSched = DutySchedule::create(['teacher_id' => $tOther->id, 'day' => 'selasa']);

        $this->actingAs($this->admin())->delete('/admin/users/'.$u->id)->assertRedirect(route('admin.users.index'));
        $this->assertNull(UserBaru::find($u->id));
        $this->assertNull(Teacher::where('user_id', $u->id)->first());
        $this->assertEquals(0, DutySchedule::where('teacher_id', $t->id)->count(), 'Jadwal milik guru terhapus.');
        $this->assertNotNull(DutySchedule::find($otherSched->id), 'Jadwal guru lain tetap ada.');
    }

    public function test_3_assigned_reports_released_not_deleted(): void
    {
        $su = $this->mkTarget('s2', 'siswa');
        $st = Student::create(['user_id' => $su->id, 'nis' => 'DELNIS002', 'class_id' => $this->ids['class']]);
        $gu = $this->mkTarget('g2', 'guru');
        $t = Teacher::create(['user_id' => $gu->id, 'nip' => 'DELNIP002']);
        $rep = Report::create(['student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $this->ids['cat'], 'title' => 'DEL-Assigned', 'description' => 'd', 'location' => 'l', 'priority' => 'rendah', 'status' => 'dalam_penanganan', 'assigned_teacher_id' => $t->id]);
        ReportHistory::create(['report_id' => $rep->id, 'user_id' => $gu->id, 'action' => 'claimed', 'from_status' => 'menunggu', 'to_status' => 'dalam_penanganan', 'note' => null]);

        // Guru punya histori => hapus ditolak, laporan utuh.
        $this->actingAs($this->admin())->delete('/admin/users/'.$gu->id)->assertSessionHasErrors('user');
        $this->assertNotNull(UserBaru::find($gu->id));
        $this->assertEquals($t->id, $rep->fresh()->assigned_teacher_id, 'Penugasan tidak diubah saat hapus ditolak.');

        // Tanpa histori + tanpa jadwal: hapus guru, laporan dilepas (SET NULL) bukan dihapus.
        ReportHistory::where('report_id', $rep->id)->delete();
        $this->actingAs($this->admin())->delete('/admin/users/'.$gu->id)->assertRedirect(route('admin.users.index'));
        $this->assertNull(UserBaru::find($gu->id));
        $this->assertNotNull(Report::find($rep->id), 'Laporan tetap ada.');
        $this->assertNull($rep->fresh()->assigned_teacher_id, 'Penugasan dilepas.');
    }

    public function test_4_reports_and_history_preserved_on_blocked_delete(): void
    {
        $su = $this->mkTarget('s3', 'siswa');
        $st = Student::create(['user_id' => $su->id, 'nis' => 'DELNIS003', 'class_id' => $this->ids['class']]);
        $rep = Report::create(['student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $this->ids['cat'], 'title' => 'DEL-Keep', 'description' => 'deskripsi utuh', 'location' => 'lok', 'priority' => 'tinggi', 'status' => 'menunggu']);
        $att = $rep->attachments()->create(['file_patch' => 'reports/x.png', 'original_name' => 'x.png', 'file_size' => 10, 'mime_type' => 'image/png']);
        ReportHistory::create(['report_id' => $rep->id, 'user_id' => $su->id, 'action' => 'created', 'from_status' => null, 'to_status' => 'menunggu', 'note' => null]);

        $this->actingAs($this->admin())->delete('/admin/users/'.$su->id)->assertSessionHasErrors('user');
        $this->assertNotNull(UserBaru::find($su->id));
        $this->assertEquals('deskripsi utuh', $rep->fresh()->description);
        $this->assertNotNull(ReportAttachment::find($att->id), 'Lampiran tetap ada.');
        $this->assertEquals(1, ReportHistory::where('report_id', $rep->id)->count(), 'Histori tetap ada.');
    }

    public function test_5_delete_kepsek_when_allowed(): void
    {
        $u = $this->mkTarget('k1', 'kepala_sekolah');
        $this->actingAs($this->admin())->delete('/admin/users/'.$u->id)->assertRedirect(route('admin.users.index'));
        $this->assertNull(UserBaru::find($u->id));
    }

    public function test_6_self_delete_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->delete('/admin/users/'.$admin->id)->assertSessionHas('error');
        $this->assertNotNull(UserBaru::find($admin->id));
    }

    public function test_7_all_admin_roles_rejected(): void
    {
        // Admin lain (aktif) -> ditolak dengan pesan kebijakan.
        $admin2 = UserBaru::findOrFail($this->ids['admin2']);
        $res = $this->actingAs($this->admin())->delete('/admin/users/'.$admin2->id);
        $res->assertSessionHas('error', 'Akun dengan role Admin tidak dapat dihapus melalui fitur ini.');
        $this->assertNotNull(UserBaru::find($admin2->id));

        // Admin nonaktif lain -> tetap ditolak (kebijakan: semua admin).
        $admin2->status = 'nonaktif';
        $admin2->save();
        $res2 = $this->actingAs($this->admin())->delete('/admin/users/'.$admin2->id);
        $res2->assertSessionHas('error', 'Akun dengan role Admin tidak dapat dihapus melalui fitur ini.');
        $this->assertNotNull(UserBaru::find($admin2->id));

        // Akun sendiri (admin) -> ditolak kebijakan admin juga.
        $admin = $this->admin();
        $this->actingAs($admin)->delete('/admin/users/'.$admin->id)->assertSessionHas('error');
        $this->assertNotNull(UserBaru::find($admin->id));
    }

    public function test_7b_admin_delete_button_hidden_in_ui(): void
    {
        // Tombol hapus tidak dirender untuk baris role admin; baris
        // non-admin tetap punya aksi hapus (cari via parameter search).
        $html = $this->actingAs($this->admin())->get('/admin/users?search=del_admin2')->assertStatus(200)->getContent();
        $admin2 = UserBaru::findOrFail($this->ids['admin2']);
        $this->assertStringContainsString(route('admin.users.edit', $admin2), $html, 'Aksi edit admin tetap ada.');
        $this->assertStringNotContainsString('action="'.route('admin.users.destroy', $admin2).'"', $html, 'Form hapus admin tidak boleh dirender.');
    }

    public function test_8_non_admin_cannot_delete(): void
    {
        $siswa = $this->mkTarget('s4', 'siswa');
        Student::create(['user_id' => $siswa->id, 'nis' => 'DELNIS004', 'class_id' => $this->ids['class']]);
        $victim = $this->mkTarget('v1', 'siswa');
        $this->actingAs($siswa)->delete('/admin/users/'.$victim->id)->assertStatus(403);
        $this->assertNotNull(UserBaru::find($victim->id));

        $guru = $this->mkTarget('g3', 'guru');
        Teacher::create(['user_id' => $guru->id, 'nip' => 'DELNIP003']);
        $this->actingAs($guru)->delete('/admin/users/'.$victim->id)->assertStatus(403);
        $this->assertNotNull(UserBaru::find($victim->id));
    }

    public function test_9_missing_user_404(): void
    {
        $this->actingAs($this->admin())->delete('/admin/users/999999')->assertStatus(404);
    }

    public function test_10_constraint_violation_no_partial(): void
    {
        // Siswa berelasi laporan: hapus ditolak sebelum ada perubahan.
        $su = $this->mkTarget('s5', 'siswa');
        $st = Student::create(['user_id' => $su->id, 'nis' => 'DELNIS005', 'class_id' => $this->ids['class']]);
        Report::create(['student_id' => $st->id, 'class_id' => $st->class_id, 'category_id' => $this->ids['cat'], 'title' => 'DEL-Partial', 'description' => 'd', 'location' => 'l', 'priority' => 'rendah', 'status' => 'menunggu']);
        $this->actingAs($this->admin())->delete('/admin/users/'.$su->id)->assertSessionHasErrors('user');
        $this->assertNotNull(UserBaru::find($su->id));
        $this->assertNotNull(Student::where('user_id', $su->id)->first(), 'Profil tidak terhapus parsial.');
        $this->assertEquals(1, Report::where('student_id', $st->id)->count());
    }

    public function test_11_double_submit_safe(): void
    {
        $u = $this->mkTarget('s6', 'siswa');
        Student::create(['user_id' => $u->id, 'nis' => 'DELNIS006', 'class_id' => $this->ids['class']]);
        $this->actingAs($this->admin())->delete('/admin/users/'.$u->id)->assertRedirect(route('admin.users.index'));
        // Kirim ulang: route-model-binding 404, tanpa efek samping.
        $this->actingAs($this->admin())->delete('/admin/users/'.$u->id)->assertStatus(404);
        $this->assertNull(UserBaru::find($u->id));
    }

    public function test_12_confirm_dialog_shows_account_info(): void
    {
        $u = $this->mkTarget('s7', 'siswa');
        $html = $this->actingAs($this->admin())->get('/admin/users')->assertStatus(200)->getContent();
        $this->assertStringContainsString($u->username, $html);
        // Dialog konfirmasi informatif (modal generik + pesan per akun).
        $this->assertStringContainsString('slConfirmModal', $html, 'Modal konfirmasi harus ada.');
        $this->assertStringContainsString('Hapus akun '.$u->name, $html, 'Pesan harus memuat nama akun.');
        $this->assertStringContainsString('tidak dapat digunakan untuk login', $html);
        $this->assertStringContainsString('tetap dipertahankan', $html);
        // Varian guru menyebut jadwal eksplisit; pastikan ada di halaman.
        $g = $this->mkTarget('g5', 'guru');
        Teacher::create(['user_id' => $g->id, 'nip' => 'DELNIP005']);
        $html2 = $this->actingAs($this->admin())->get('/admin/users?search=del_target_g5')->assertStatus(200)->getContent();
        $this->assertStringContainsString('jadwal guru lain tidak terpengaruh', $html2, 'Pesan guru harus menyebut jadwal.');
        $this->assertStringContainsString('tidak ikut dihapus', $html2);
    }

    public function test_13b_guru_nonaktif_multi_schedule_deletable(): void
    {
        // Guru nonaktif dengan BEBERAPA jadwal: semua miliknya terhapus,
        // jadwal guru lain utuh, akun+profil terhapus.
        $u = $this->mkTarget('g4', 'guru', 'nonaktif');
        $t = Teacher::create(['user_id' => $u->id, 'nip' => 'DELNIP004']);
        DutySchedule::create(['teacher_id' => $t->id, 'day' => 'senin']);
        DutySchedule::create(['teacher_id' => $t->id, 'day' => 'rabu']);
        DutySchedule::create(['teacher_id' => $t->id, 'day' => 'jumat']);
        $other = $this->mkTarget('g4b', 'guru');
        $tOther = Teacher::create(['user_id' => $other->id, 'nip' => 'DELNIP004B']);
        $otherSched = DutySchedule::create(['teacher_id' => $tOther->id, 'day' => 'kamis']);

        $this->actingAs($this->admin())->delete('/admin/users/'.$u->id)->assertRedirect(route('admin.users.index'));
        $this->assertNull(UserBaru::find($u->id));
        $this->assertNull(Teacher::where('user_id', $u->id)->first());
        $this->assertEquals(0, DutySchedule::where('teacher_id', $t->id)->count(), 'Semua jadwal milik target terhapus.');
        $this->assertNotNull(DutySchedule::find($otherSched->id), 'Jadwal guru lain tetap utuh.');
    }

    public function test_13_siswa_nonaktif_and_kepsek_nonaktif_deletable(): void
    {
        $s = $this->mkTarget('s8', 'siswa', 'nonaktif');
        Student::create(['user_id' => $s->id, 'nis' => 'DELNIS008', 'class_id' => $this->ids['class']]);
        $this->actingAs($this->admin())->delete('/admin/users/'.$s->id)->assertRedirect(route('admin.users.index'));
        $this->assertNull(UserBaru::find($s->id));

        $k = $this->mkTarget('k2', 'kepala_sekolah', 'nonaktif');
        $this->actingAs($this->admin())->delete('/admin/users/'.$k->id)->assertRedirect(route('admin.users.index'));
        $this->assertNull(UserBaru::find($k->id));
    }
}
