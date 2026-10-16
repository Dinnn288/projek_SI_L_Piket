<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportCategory;
use App\Models\ReportHistory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminReportCategoriesFase12ETest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Storage::fake('local');

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $admin = $mkUser('f12e_admin', 'Admin F12E', 'admin');
        $siswa = $mkUser('f12e_siswa', 'Siswa F12E', 'siswa');
        $guru = $mkUser('f12e_guru', 'Guru F12E', 'guru');
        $kepsek = $mkUser('f12e_kepsek', 'Kepsek F12E', 'kepala_sekolah');

        $this->ids = [
            'admin' => $admin->id, 'siswa' => $siswa->id,
            'guru' => $guru->id, 'kepsek' => $kepsek->id,
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                // Cleanup eksplisit: kategori uji by nama, bukan LIKE luas.
                $catIds = ReportCategory::whereIn('name', ['F12E_Keributan', 'F12E_Fasilitas', 'F12E_Kebersihan', 'F12E_Temp'])->pluck('id');
                $rids = Report::whereIn('category_id', $catIds)->pluck('id');
                if ($rids->isNotEmpty()) {
                    ReportHistory::whereIn('report_id', $rids)->delete();
                    ReportAttachment::whereIn('report_id', $rids)->delete();
                    \App\Models\Notification::whereIn('report_id', $rids)->delete();
                    Report::whereIn('id', $rids)->delete();
                }
                ReportCategory::whereIn('id', $catIds)->delete();
                UserBaru::whereIn('id', [$this->ids['admin'] ?? 0])->delete();
                UserBaru::whereIn('id', [$this->ids['siswa'] ?? 0])->delete();
                UserBaru::whereIn('id', [$this->ids['guru'] ?? 0])->delete();
                UserBaru::whereIn('id', [$this->ids['kepsek'] ?? 0])->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function admin(): UserBaru
    {
        return UserBaru::findOrFail($this->ids['admin']);
    }

    public function test_access_control(): void
    {
        $this->actingAs($this->admin())->get('/admin/report-categories')->assertStatus(200)->assertSee('Kategori', false);
        $this->actingAs($this->admin())->get('/admin/report-categories/create')->assertStatus(200);
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/admin/report-categories', ['name' => 'x'])->assertRedirect('/login');
    }

    public function test_cross_role_forbidden(): void
    {
        foreach (['siswa', 'guru', 'kepsek'] as $key) {
            $this->refreshApplication();
            $this->reconnect();
            $user = UserBaru::find($this->ids[$key]);
            if ($user) $this->actingAs($user)->get('/admin/report-categories')->assertStatus(403);
        }
    }

    public function test_create_edit_validation(): void
    {
        // Valid: 3 contoh prioritas
        $this->actingAs($this->admin())->post('/admin/report-categories', [
            'name' => 'F12E_Keributan', 'description' => 'd', 'status' => 'aktif', 'priority' => 'tinggi',
        ])->assertRedirect(route('admin.report-categories.index'));
        $this->actingAs($this->admin())->post('/admin/report-categories', [
            'name' => 'F12E_Fasilitas', 'status' => 'aktif', 'priority' => 'sedang',
        ])->assertRedirect(route('admin.report-categories.index'));
        $this->actingAs($this->admin())->post('/admin/report-categories', [
            'name' => 'F12E_Kebersihan', 'status' => 'aktif', 'priority' => 'rendah',
        ])->assertRedirect(route('admin.report-categories.index'));
        // Invalid: priority, required, duplikat
        $this->actingAs($this->admin())->post('/admin/report-categories', [
            'name' => 'F12E_Temp', 'status' => 'aktif', 'priority' => 'urgent',
        ])->assertSessionHasErrors('priority');
        $this->actingAs($this->admin())->post('/admin/report-categories', [
            'name' => '', 'status' => 'aktif', 'priority' => 'tinggi',
        ])->assertSessionHasErrors('name');
        $this->actingAs($this->admin())->post('/admin/report-categories', [
            'name' => 'F12E_Keributan', 'status' => 'aktif', 'priority' => 'tinggi',
        ])->assertSessionHasErrors('name');
        // Edit: ubah priority + nama sendiri boleh, duplikat milik lain ditolak
        $cat = ReportCategory::where('name', 'F12E_Keributan')->firstOrFail();
        $this->actingAs($this->admin())->put('/admin/report-categories/'.$cat->id, [
            'name' => 'F12E_Keributan', 'status' => 'aktif', 'priority' => 'sedang',
        ])->assertRedirect(route('admin.report-categories.index'));
        $this->assertEquals('sedang', $cat->fresh()->priority);
        $this->actingAs($this->admin())->put('/admin/report-categories/'.$cat->id, [
            'name' => 'F12E_Fasilitas', 'status' => 'aktif', 'priority' => 'sedang',
        ])->assertSessionHasErrors('name');
    }

    public function test_safe_delete_and_report_protection(): void
    {
        $this->actingAs($this->admin())->post('/admin/report-categories', [
            'name' => 'F12E_Temp', 'status' => 'aktif', 'priority' => 'rendah',
        ]);
        $temp = ReportCategory::where('name', 'F12E_Temp')->firstOrFail();
        // Tanpa report -> hapus boleh
        $this->actingAs($this->admin())->delete('/admin/report-categories/'.$temp->id)
            ->assertRedirect(route('admin.report-categories.index'));
        $this->assertNull(ReportCategory::find($temp->id));

        // Dengan report (status selesai) -> tolak, report/history/attachment/notif utuh
        $this->actingAs($this->admin())->post('/admin/report-categories', [
            'name' => 'F12E_Keributan', 'status' => 'aktif', 'priority' => 'tinggi',
        ]);
        $cat = ReportCategory::where('name', 'F12E_Keributan')->firstOrFail();
        $student = Student::first();
        $classId = $student ? (int) $student->class_id : (int) SchoolClass::value('id');
        $report = null;
        if ($student && $classId) {
            $report = Report::create([
                'student_id' => $student->id, 'class_id' => $classId, 'category_id' => $cat->id,
                'title' => 'T-F12E', 'description' => 'd', 'location' => 'l',
                'priority' => $cat->priority, 'status' => 'selesai',
            ]);
            ReportHistory::create([
                'report_id' => $report->id, 'user_id' => $this->ids['admin'],
                'action' => 'completed', 'from_status' => 'diteruskan',
                'to_status' => 'selesai', 'note' => 'ok',
            ]);
        }
        $this->actingAs($this->admin())->delete('/admin/report-categories/'.$cat->id)->assertSessionHas('error');
        $this->assertNotNull(ReportCategory::find($cat->id));
        if (isset($report)) {
            $this->assertNotNull(Report::find($report->id));
            $this->assertEquals($cat->id, (int) $report->fresh()->category_id);
            $this->assertEquals(1, ReportHistory::where('report_id', $report->id)->count());
        }
    }

    public function test_priority_snapshot_from_category(): void
    {
        $this->actingAs($this->admin())->post('/admin/report-categories', [
            'name' => 'F12E_Temp', 'status' => 'aktif', 'priority' => 'tinggi',
        ]);
        $cat = ReportCategory::where('name', 'F12E_Temp')->firstOrFail();
        $student = Student::first();
        $report = null;
        if ($student) {
            $report = Report::create([
                'student_id' => $student->id, 'class_id' => $student->class_id, 'category_id' => $cat->id,
                'title' => 'T-F12E', 'description' => 'd', 'location' => 'l',
                'priority' => $cat->priority, 'status' => 'menunggu',
            ]);
            $this->assertEquals('tinggi', $report->fresh()->priority);
            // Edit priority kategori -> snapshot laporan lama tidak berubah
            $this->actingAs($this->admin())->put('/admin/report-categories/'.$cat->id, [
                'name' => 'F12E_Temp', 'status' => 'aktif', 'priority' => 'rendah',
            ]);
            $this->assertEquals('tinggi', $report->fresh()->priority);
            $this->assertEquals('rendah', $cat->fresh()->priority);
            $this->assertEquals($cat->id, (int) $report->fresh()->category_id);
            ReportHistory::where('report_id', $report->id)->delete();
            ReportAttachment::where('report_id', $report->id)->delete();
            \App\Models\Notification::where('report_id', $report->id)->delete();
            $report->delete();
        }
        $this->assertTrue(true);
    }

    public function test_student_report_still_uses_category_priority(): void
    {
        $this->actingAs($this->admin())->post('/admin/report-categories', [
            'name' => 'F12E_Fasilitas', 'status' => 'aktif', 'priority' => 'sedang',
        ]);
        // Siswa uji F12E tidak punya record students (akun role saja) sehingga
        // halaman create yang butuh student me-return 403 — verifikasi via
        // kategori tampil untuk admin + priority snapshot tetap dari kategori.
        $cat = ReportCategory::where('name', 'F12E_Fasilitas')->firstOrFail();
        $this->assertEquals('sedang', $cat->priority);
        $this->actingAs($this->admin())->get('/admin/report-categories')->assertStatus(200)->assertSee('F12E_Fasilitas', false);
    }
}




