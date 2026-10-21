<?php

namespace Tests\Feature;

use App\Models\ClassDutyReport;
use App\Models\ClassDutyReportAttachment;
use App\Models\ClassDutyReportHistory;
use App\Models\ClassDutySchedule;
use App\Models\DutySchedule;
use App\Models\Major;
use App\Models\Notification;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Services\ClassDutyService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClassDutyPiketKelasTest extends TestCase
{
    private function reconnect(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket_test']);
        DB::reconnect('mysql');
        \Tests\TestDatabaseGuard::assertTestDatabaseSafe();
    }

    private function makeUser(string $username, string $role): UserBaru
    {
        return UserBaru::where('username', $username)->first()
            ?: UserBaru::create([
                'name' => 'Test '.$username,
                'username' => $username,
                'password' => Hash::make('password123'),
                'role' => $role,
                'status' => 'aktif',
            ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        Storage::fake('local');
        // Senin 2026-10-12 09:00 Asia/Jakarta agar todayDay() = senin.
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 9, 0, 0, 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            $reportIds = ClassDutyReport::whereHas('student', function ($q) {
                $q->whereIn('nis', ['CDPK_NIS_A', 'CDPK_NIS_B']);
            })->pluck('id');
            if ($reportIds->isNotEmpty()) {
                ClassDutyReportAttachment::whereIn('class_duty_report_id', $reportIds)->delete();
                ClassDutyReportHistory::whereIn('class_duty_report_id', $reportIds)->delete();
                // Hapus notifikasi piket kelas terkait agar tidak menumpuk.
                Notification::where('title', 'like', 'Laporan Piket%')->delete();
                ClassDutyReport::whereIn('id', $reportIds)->delete();
            }
            $schedClasses = SchoolClass::whereIn('name', ['CDPK_KELAS_A', 'CDPK_KELAS_B'])->pluck('id');
            if ($schedClasses->isNotEmpty()) {
                ClassDutySchedule::whereIn('class_id', $schedClasses)->delete();
            }
            $studentIds = Student::whereIn('nis', ['CDPK_NIS_A', 'CDPK_NIS_B'])->pluck('id');
            $userIds = Student::whereIn('nis', ['CDPK_NIS_A', 'CDPK_NIS_B'])->pluck('user_id');
            if ($studentIds->isNotEmpty()) {
                Student::whereIn('id', $studentIds)->delete();
            }
            SchoolClass::whereIn('name', ['CDPK_KELAS_A', 'CDPK_KELAS_B'])->delete();
            Major::where('name', 'CDPK_MAJOR')->delete();
            $guruUsers = UserBaru::whereIn('username', ['cdpk_admin', 'cdpk_guru', 'cdpk_siswa_a', 'cdpk_siswa_b'])->pluck('id');
            $teacherIds = Teacher::whereIn('user_id', $guruUsers)->pluck('id');
            if ($teacherIds->isNotEmpty()) {
                DutySchedule::whereIn('teacher_id', $teacherIds)->delete();
                Teacher::whereIn('id', $teacherIds)->delete();
            }
            Notification::whereIn('user_id', $guruUsers)->delete();
            UserBaru::whereIn('username', ['cdpk_admin', 'cdpk_guru', 'cdpk_siswa_a', 'cdpk_siswa_b'])->delete();
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedScenario(): array
    {
        $this->assertEquals('senin', ClassDutyService::todayDay());

        $admin = $this->makeUser('cdpk_admin', 'admin');
        $guruUser = $this->makeUser('cdpk_guru', 'guru');
        $teacher = Teacher::where('user_id', $guruUser->id)->first()
            ?: Teacher::create(['user_id' => $guruUser->id, 'nip' => 'CDPK_NIP_GURU']);
        DutySchedule::firstOrCreate(['teacher_id' => $teacher->id, 'day' => 'senin']);

        $major = Major::firstOrCreate(['name' => 'CDPK_MAJOR'], ['status' => 'aktif']);
        $classA = SchoolClass::where('name', 'CDPK_KELAS_A')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'CDPK_KELAS_A', 'status' => 'aktif']);
        $classB = SchoolClass::where('name', 'CDPK_KELAS_B')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'CDPK_KELAS_B', 'status' => 'aktif']);
        // Hanya kelas A terjadwal Senin; kelas B tanpa jadwal.
        ClassDutySchedule::firstOrCreate(['class_id' => $classA->id, 'day' => 'senin']);

        $siswaUserA = $this->makeUser('cdpk_siswa_a', 'siswa');
        $studentA = Student::where('user_id', $siswaUserA->id)->first();
        if (! $studentA) {
            $studentA = Student::create(['user_id' => $siswaUserA->id, 'nis' => 'CDPK_NIS_A', 'class_id' => $classA->id]);
        }
        $siswaUserA = $studentA->userBaru;

        $siswaUserB = $this->makeUser('cdpk_siswa_b', 'siswa');
        $studentB = Student::where('user_id', $siswaUserB->id)->first();
        if (! $studentB) {
            $studentB = Student::create(['user_id' => $siswaUserB->id, 'nis' => 'CDPK_NIS_B', 'class_id' => $classB->id]);
        }
        $siswaUserB = $studentB->userBaru;

        // Buat jadwal siswa untuk hari ini (Senin) sesuai jadwal kelas
        // Siswa A di kelas A yang terjadwal Senin
        \App\Models\ClassDutyStudentSchedule::firstOrCreate([
            'class_id' => $classA->id,
            'student_id' => $studentA->id,
            'day' => 'senin',
        ]);
        // Siswa B di kelas B yang TIDAK terjadwal hari ini (kelas B tidak punya jadwal)
        // Tidak dibuatkan jadwal siswa untuk B

        return compact('admin', 'guruUser', 'teacher', 'major', 'classA', 'classB', 'siswaUserA', 'siswaUserB');
    }

    public function test_admin_can_manage_schedule_and_duplicate_rejected(): void
    {
        $s = $this->seedScenario();

        // Duplikat (classA, senin) ditolak.
        $this->actingAs($s['admin'])->post('/admin/class-duty-schedules', [
            'class_id' => $s['classA']->id,
            'day' => 'senin',
        ])->assertSessionHasErrors('day');

        // Jadwal baru (classA, selasa) sukses.
        $this->actingAs($s['admin'])->post('/admin/class-duty-schedules', [
            'class_id' => $s['classA']->id,
            'day' => 'selasa',
        ])->assertRedirect(route('admin.class-duty-schedules.index'));
        $this->assertTrue(ClassDutySchedule::where('class_id', $s['classA']->id)->where('day', 'selasa')->exists());

        // Halaman index 200 dan memuat kedua hari.
        $this->actingAs($s['admin'])->get('/admin/class-duty-schedules')
            ->assertStatus(200)
            ->assertSee('CDPK_KELAS_A', false);
    }

    public function test_guru_dashboard_lists_all_classes_including_unscheduled(): void
    {
        $s = $this->seedScenario();

        $res = $this->actingAs($s['guruUser'])->get('/guru/piket-kelas');
        $res->assertStatus(200);
        // Kelas terjadwal dan tidak dijadwalkan sama-sama muncul.
        $res->assertSee('CDPK_KELAS_A', false);
        $res->assertSee('CDPK_KELAS_B', false);
        $res->assertSee('Tidak dijadwalkan', false);
        $res->assertSee('Belum melapor', false);

        // Detail kelas A menampilkan siswa bertugas hari ini (Senin).
        $detail = $this->actingAs($s['guruUser'])->get('/guru/piket-kelas/kelas/'.$s['classA']->id);
        $detail->assertStatus(200);
        $detail->assertSee('CDPK_NIS_A', false);

        // Detail kelas B (tidak terjadwal) menampilkan pesan jelas.
        $detailB = $this->actingAs($s['guruUser'])->get('/guru/piket-kelas/kelas/'.$s['classB']->id);
        $detailB->assertStatus(200);
        $detailB->assertSee('tidak dijadwalkan', false);
    }

    public function test_siswa_report_flow_verify_return_resubmit_approve(): void
    {
        $s = $this->seedScenario();

        // Siswa B (kelas tidak terjadwal) tidak dapat membuka form.
        $this->actingAs($s['siswaUserB'])->get('/siswa/piket-kelas/lapor')
            ->assertRedirect(route('siswa.class-duty.index'));

        // Siswa A (terjadwal) dapat membuka form dan mengirim.
        $this->actingAs($s['siswaUserA'])->get('/siswa/piket-kelas/lapor')->assertStatus(200);
        $store = $this->actingAs($s['siswaUserA'])->post('/siswa/piket-kelas', [
            'checklist' => ['menyapu', 'sampah'],
            'notes' => 'Kelas bersih.',
        ]);
        $store->assertRedirect(route('siswa.class-duty.index'));
        $report = ClassDutyReport::where('class_id', $s['classA']->id)
            ->where('report_date', '2026-10-12')->firstOrFail();
        $this->assertEquals('menunggu_verifikasi', $report->status);
        $this->assertEquals(['menyapu', 'sampah'], $report->checklist);

        // Laporan ganda kelas+tanggal sama ditolak (jaring aplikasi).
        $this->actingAs($s['siswaUserA'])->post('/siswa/piket-kelas', [
            'checklist' => ['menyapu'],
        ])->assertRedirect(route('siswa.class-duty.index'));

        // Guru bertugas mengembalikan dengan catatan.
        $this->actingAs($s['guruUser'])->post('/guru/piket-kelas/laporan/'.$report->id.'/kembalikan', [
            'note' => 'Foto papan tulis kurang.',
        ])->assertRedirect();
        $this->assertEquals('perlu_diperbaiki', $report->fresh()->status);
        $this->assertEquals('Foto papan tulis kurang.', $report->fresh()->return_note);

        // Siswa memperbaiki pada laporan yang sama (bukan laporan baru).
        $countBefore = ClassDutyReport::where('class_id', $s['classA']->id)->where('report_date', '2026-10-12')->count();
        $this->actingAs($s['siswaUserA'])->post('/siswa/piket-kelas/'.$report->id.'/kirim-ulang', [
            'checklist' => ['menyapu', 'sampah', 'papan_tulis'],
            'notes' => 'Sudah dilengkapi.',
        ])->assertRedirect(route('siswa.class-duty.index'));
        $this->assertEquals($countBefore, ClassDutyReport::where('class_id', $s['classA']->id)->where('report_date', '2026-10-12')->count());
        $this->assertEquals('menunggu_verifikasi', $report->fresh()->status);
        $this->assertNull($report->fresh()->return_note);

        // Guru menyetujui → sudah piket + pencatatan verifikator.
        $this->actingAs($s['guruUser'])->post('/guru/piket-kelas/laporan/'.$report->id.'/setujui', [
            'note' => 'Lengkap dan bersih.',
        ])->assertRedirect();
        $fresh = $report->fresh();
        $this->assertEquals('disetujui', $fresh->status);
        $this->assertEquals($s['teacher']->id, (int) $fresh->verified_by_teacher_id);
        $this->assertNotNull($fresh->verified_at);

        // Riwayat keputusan lengkap dan dapat ditelusuri.
        $actions = ClassDutyReportHistory::where('class_duty_report_id', $report->id)->pluck('action')->all();
        $this->assertContains('created', $actions);
        $this->assertContains('returned', $actions);
        $this->assertContains('resubmitted', $actions);
        $this->assertContains('approved', $actions);
    }

    public function test_authorization_blocks_cross_class_and_self_approve(): void
    {
        $s = $this->seedScenario();

        $this->actingAs($s['siswaUserA'])->post('/siswa/piket-kelas', [
            'checklist' => ['menyapu'],
        ])->assertRedirect();
        $report = ClassDutyReport::where('class_id', $s['classA']->id)->where('report_date', '2026-10-12')->firstOrFail();

        // Siswa kelas B tidak dapat kirim-ulang laporan kelas A (404).
        $this->actingAs($s['siswaUserB'])->post('/siswa/piket-kelas/'.$report->id.'/kirim-ulang', [
            'checklist' => ['menyapu'],
        ])->assertStatus(404);

        // Siswa tidak dapat memakai endpoint guru (403 via role).
        $this->actingAs($s['siswaUserA'])->post('/guru/piket-kelas/laporan/'.$report->id.'/setujui', [
            'note' => 'coba setujui sendiri',
        ])->assertStatus(403);

        // Admin tidak dapat memakai endpoint guru (403 via role).
        $this->actingAs($s['admin'])->post('/guru/piket-kelas/laporan/'.$report->id.'/setujui', [
            'note' => 'admin coba',
        ])->assertStatus(403);
    }

    public function test_old_issue_report_flow_untouched(): void
    {
        // Guard regresi ringan: route laporan masalah masih terdaftar dan
        // halaman index guru/siswa tetap 200 (tanpa mengubah alur).
        $s = $this->seedScenario();
        $this->actingAs($s['siswaUserA'])->get('/siswa/laporan')->assertStatus(200);
        $this->actingAs($s['guruUser'])->get('/guru/laporan')->assertStatus(200);
        $this->actingAs($s['guruUser'])->get('/guru')->assertStatus(200);
        $this->actingAs($s['siswaUserA'])->get('/siswa')->assertStatus(200);
    }

    // ===== Unit tests eksplisit untuk method ClassDutyService baru =====

    public function test_dayFromDate_handles_senin_jumat_and_weekend(): void
    {
        // Senin
        $this->assertEquals('senin', ClassDutyService::dayFromDate('2026-10-12'));
        // Selasa
        $this->assertEquals('selasa', ClassDutyService::dayFromDate('2026-10-13'));
        // Rabu
        $this->assertEquals('rabu', ClassDutyService::dayFromDate('2026-10-14'));
        // Kamis
        $this->assertEquals('kamis', ClassDutyService::dayFromDate('2026-10-15'));
        // Jumat
        $this->assertEquals('jumat', ClassDutyService::dayFromDate('2026-10-16'));
        // Sabtu -> null
        $this->assertNull(ClassDutyService::dayFromDate('2026-10-17'));
        // Minggu -> null
        $this->assertNull(ClassDutyService::dayFromDate('2026-10-18'));
    }

    public function test_isStudentScheduled_checks_student_day(): void
    {
        $s = $this->seedScenario();
        $studentA = Student::where('nis', 'CDPK_NIS_A')->firstOrFail();

        // Siswa A dijadwalkan Senin
        $this->assertTrue(ClassDutyService::isStudentScheduled($studentA->id, 'senin'));
        // Siswa A TIDAK dijadwalkan Selasa
        $this->assertFalse(ClassDutyService::isStudentScheduled($studentA->id, 'selasa'));
        // Sabtu/Minggu selalu false
        $this->assertFalse(ClassDutyService::isStudentScheduled($studentA->id, 'sabtu'));
        $this->assertFalse(ClassDutyService::isStudentScheduled($studentA->id, 'minggu'));
    }

    public function test_isStudentScheduledInClass_validates_class_match(): void
    {
        $s = $this->seedScenario();
        $studentA = Student::where('nis', 'CDPK_NIS_A')->firstOrFail();
        $classA = SchoolClass::where('name', 'CDPK_KELAS_A')->firstOrFail();
        $classB = SchoolClass::where('name', 'CDPK_KELAS_B')->firstOrFail();

        // Siswa A di kelas A dijadwalkan Senin
        $this->assertTrue(ClassDutyService::isStudentScheduledInClass($studentA->id, $classA->id, 'senin'));
        // Siswa A di kelas B (salah) -> false
        $this->assertFalse(ClassDutyService::isStudentScheduledInClass($studentA->id, $classB->id, 'senin'));
        // Hari tidak valid -> false
        $this->assertFalse(ClassDutyService::isStudentScheduledInClass($studentA->id, $classA->id, 'sabtu'));
    }

    public function test_hasStudentsScheduled_counts_students_per_class_day(): void
    {
        $s = $this->seedScenario();
        $classA = SchoolClass::where('name', 'CDPK_KELAS_A')->firstOrFail();
        $classB = SchoolClass::where('name', 'CDPK_KELAS_B')->firstOrFail();

        // Kelas A punya siswa terjadwal Senin
        $this->assertTrue(ClassDutyService::hasStudentsScheduled($classA->id, 'senin'));
        // Kelas B tidak punya siswa terjadwal Senin
        $this->assertFalse(ClassDutyService::hasStudentsScheduled($classB->id, 'senin'));
        // Sabtu/Minggu selalu false
        $this->assertFalse(ClassDutyService::hasStudentsScheduled($classA->id, 'sabtu'));
    }

    public function test_statusFor_returns_tidak_ada_piket_when_class_scheduled_but_no_students(): void
    {
        $s = $this->seedScenario();
        $classB = SchoolClass::where('name', 'CDPK_KELAS_B')->firstOrFail();

        // Tambah jadwal kelas B untuk Senin TANPA siswa terjadwal
        \App\Models\ClassDutySchedule::firstOrCreate(['class_id' => $classB->id, 'day' => 'senin']);

        // Status harus 'tidak_ada_piket' (kelas terjadwal tapi 0 siswa terjadwal)
        $status = ClassDutyService::statusFor($classB->id, '2026-10-12', 'senin');
        $this->assertEquals('tidak_ada_piket', $status);

        // Cleanup
        \App\Models\ClassDutySchedule::where('class_id', $classB->id)->where('day', 'senin')->delete();
    }

    public function test_student_not_scheduled_in_wrong_class_cannot_report(): void
    {
        $s = $this->seedScenario();

        // Siswa B di kelas B tidak dijadwalkan -> tidak bisa kirim laporan
        $this->actingAs($s['siswaUserB'])->get('/siswa/piket-kelas/lapor')
            ->assertRedirect(route('siswa.class-duty.index'));

        // Siswa A di kelas A dijadwalkan -> bisa kirim
        $this->actingAs($s['siswaUserA'])->get('/siswa/piket-kelas/lapor')->assertStatus(200);
    }

    public function test_sabtu_and_minggu_always_tidak_dijadwalkan(): void
    {
        // Sabtu
        Carbon::setTestNow(Carbon::create(2026, 10, 17, 9, 0, 0, 'Asia/Jakarta')); // Sabtu
        $this->assertNull(ClassDutyService::todayDay());
        $this->assertEquals('tidak_dijadwalkan', ClassDutyService::statusFor(1, '2026-10-17', 'sabtu'));

        // Minggu
        Carbon::setTestNow(Carbon::create(2026, 10, 18, 9, 0, 0, 'Asia/Jakarta')); // Minggu
        $this->assertNull(ClassDutyService::todayDay());
        $this->assertEquals('tidak_dijadwalkan', ClassDutyService::statusFor(1, '2026-10-18', 'minggu'));

        Carbon::setTestNow();
    }
}
