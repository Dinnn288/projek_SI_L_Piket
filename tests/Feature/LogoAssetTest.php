<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cakupan logo SI-LPiket: file asset ada, halaman login memuat logo +
 * favicon, dan sidebar semua role memuat logo + favicon. Murni validasi
 * visual/asset; tidak menyentuh backend.
 */
class LogoAssetTest extends TestCase
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

        $mkUser = function (string $u, string $name, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        $admin = $mkUser('flt_admin', 'Admin FLT', 'admin');
        $siswa = $mkUser('flt_siswa', 'Siswa FLT', 'siswa');
        $guru = $mkUser('flt_guru', 'Guru FLT', 'guru');
        $kepsek = $mkUser('flt_kepsek', 'Kepsek FLT', 'kepala_sekolah');

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_FLT'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_FLT')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_FLT', 'status' => 'aktif']);
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'FLTNIS001', 'class_id' => $class->id]);
        }
        if (! Teacher::where('user_id', $guru->id)->exists()) {
            Teacher::create(['user_id' => $guru->id, 'nip' => 'FLTNIP001']);
        }

        $this->ids = [
            'admin' => $admin->id, 'siswa' => $siswa->id, 'guru' => $guru->id, 'kepsek' => $kepsek->id,
            'major' => $major->id, 'class' => $class->id,
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                Student::where('nis', 'FLTNIS001')->delete();
                $gids = UserBaru::whereIn('username', ['flt_guru'])->pluck('id');
                Teacher::whereIn('user_id', $gids)->delete();
                UserBaru::whereIn('username', ['flt_admin', 'flt_siswa', 'flt_guru', 'flt_kepsek'])->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    public function test_logo_asset_files_exist(): void
    {
        $logo = public_path('images/Logo-si-lpiket.jpg');
        $favicon = public_path('favicon.ico');

        $this->assertFileExists($logo, 'Logo sidebar/login harus ada di public/images.');
        $this->assertGreaterThan(0, filesize($logo), 'File logo tidak boleh kosong.');
        $this->assertFileExists($favicon, 'Favicon harus ada di public/.');
        $this->assertGreaterThan(0, filesize($favicon), 'File favicon tidak boleh kosong (0-byte seperti sebelumnya).');

        $info = getimagesize($logo);
        $this->assertNotFalse($info, 'Logo harus file gambar valid.');
        $this->assertGreaterThanOrEqual(100, $info[0], 'Lebar logo cukup untuk sidebar/login.');
    }

    public function test_login_contains_logo_and_favicon(): void
    {
        $res = $this->get('/login');
        $res->assertStatus(200);
        $res->assertSee('images/Logo-si-lpiket.jpg', false);
        $res->assertSee('alt="Logo SI-LPiket"', false);
        $res->assertSee('favicon.ico', false);
        // Fitur existing tetap ada: form login + show/hide password.
        $res->assertSee('name="username"', false);
        $res->assertSee('data-sl-pw-toggle', false);
    }

    public function test_sidebar_logo_all_roles(): void
    {
        $cases = [
            'admin' => '/admin',
            'siswa' => '/siswa',
            'guru' => '/guru',
            'kepsek' => '/kepala-sekolah',
        ];

        foreach ($cases as $key => $url) {
            $user = UserBaru::find($this->ids[$key]);
            $this->assertNotNull($user, 'Prekondisi: user '.$key.' harus ada.');
            $res = $this->actingAs($user)->get($url);
            $res->assertStatus(200);
            $res->assertSee('images/Logo-si-lpiket.jpg', false);
            $res->assertSee('alt="Logo SI-LPiket"', false);
            $res->assertSee('favicon.ico', false);
        }
    }
}
