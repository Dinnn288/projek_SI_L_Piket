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
 * Cakupan Show/Hide password: setiap halaman yang me-render input password
 * wajib menyertakan tombol toggle independen per field, tanpa mengubah
 * backend (submit tetap mengirim type=password, default hidden).
 */
class PasswordToggleTest extends TestCase
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
        $admin = $mkUser('fpt_admin', 'Admin FPT', 'admin');
        $siswa = $mkUser('fpt_siswa', 'Siswa FPT', 'siswa');
        $guru = $mkUser('fpt_guru', 'Guru FPT', 'guru');
        $kepsek = $mkUser('fpt_kepsek', 'Kepsek FPT', 'kepala_sekolah');

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_FPT'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_FPT')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_FPT', 'status' => 'aktif']);
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'FPTNIS001', 'class_id' => $class->id]);
        }
        if (! Teacher::where('user_id', $guru->id)->exists()) {
            Teacher::create(['user_id' => $guru->id, 'nip' => 'FPTNIP001']);
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
                Student::where('nis', 'FPTNIS001')->delete();
                $gids = UserBaru::whereIn('username', ['fpt_guru'])->pluck('id');
                Teacher::whereIn('user_id', $gids)->delete();
                UserBaru::whereIn('username', ['fpt_admin', 'fpt_siswa', 'fpt_guru', 'fpt_kepsek'])->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    /**
     * @return array{html: string, inputs: string[], toggles: string[]}
     */
    private function passwordFields(string $html): array
    {
        $inputs = [];
        $toggles = [];

        preg_match_all('/<input[^>]*type="password"[^>]*>/i', $html, $m);
        foreach ($m[0] as $tag) {
            if (preg_match('/name="([^"]+)"/', $tag, $n)) {
                $inputs[] = $n[1];
            } else {
                $inputs[] = $tag;
            }
        }

        preg_match_all('/<button[^>]*data-sl-pw-toggle[^>]*>/i', $html, $t);
        $toggles = $t[0];

        return ['html' => $html, 'inputs' => $inputs, 'toggles' => $toggles];
    }

    private function assertToggleCoverage(string $html, array $expectedNames): void
    {
        $found = $this->passwordFields($html);

        $this->assertNotEmpty($found['inputs'], 'Halaman harus memiliki input password.');
        foreach ($expectedNames as $name) {
            $this->assertContains($name, $found['inputs'], 'Input '.$name.' harus ada.');
        }

        // Setiap field password punya tepat satu toggle independen di dalam
        // wrapper yang sama (bukan toggle global).
        preg_match_all('/<div[^>]*data-sl-pw-field[^>]*>.*?<input[^>]*type="password"[^>]*>.*?<button[^>]*data-sl-pw-toggle[^>]*>.*?<\/div>/is', $html, $wrapped);
        $this->assertCount(
            count($found['inputs']),
            $wrapped[0],
            'Setiap input password harus dibungkus .sl-pw-field dengan satu toggle sendiri.'
        );

        // Toggle tidak boleh submit form dan default hidden.
        foreach ($found['toggles'] as $toggle) {
            $this->assertMatchesRegularExpression('/type="button"/i', $toggle, 'Toggle harus type=button agar tidak submit form.');
        }
        $this->assertMatchesRegularExpression('/aria-pressed="false"/', $html, 'Default toggle harus hidden (aria-pressed=false).');

        // Script reusable + stylesheet ter-load.
        $this->assertStringContainsString('sl-password.js', $html, 'Script show/hide harus dimuat.');
    }

    public function test_login_has_password_toggle(): void
    {
        $res = $this->get('/login');
        $res->assertStatus(200);
        $this->assertToggleCoverage($res->getContent(), ['password']);
    }

    public function test_admin_create_forms_have_independent_toggles(): void
    {
        $admin = UserBaru::findOrFail($this->ids['admin']);

        foreach (['/admin/users/create' => ['password'],
            '/admin/students/create' => ['password', 'password_confirmation'],
            '/admin/teachers/create' => ['password', 'password_confirmation']] as $url => $names) {
            $res = $this->actingAs($admin)->get($url);
            $res->assertStatus(200);
            $this->assertToggleCoverage($res->getContent(), $names);
        }
    }

    public function test_admin_edit_forms_have_independent_toggles(): void
    {
        $admin = UserBaru::findOrFail($this->ids['admin']);
        $siswaUser = UserBaru::findOrFail($this->ids['siswa']);
        $guruUser = UserBaru::findOrFail($this->ids['guru']);
        $student = Student::where('user_id', $siswaUser->id)->firstOrFail();
        $teacher = Teacher::where('user_id', $guruUser->id)->firstOrFail();

        foreach (['/admin/users/'.$admin->id.'/edit' => ['password', 'password_confirmation'],
            '/admin/students/'.$student->id.'/edit' => ['password', 'password_confirmation'],
            '/admin/teachers/'.$teacher->id.'/edit' => ['password', 'password_confirmation']] as $url => $names) {
            $res = $this->actingAs($admin)->get($url);
            $res->assertStatus(200);
            $this->assertToggleCoverage($res->getContent(), $names);
        }
    }

    public function test_profile_password_forms_all_roles_have_toggles(): void
    {
        $cases = [
            'admin' => '/admin/profil',
            'siswa' => '/siswa/profil',
            'guru' => '/guru/profil',
            'kepsek' => '/kepala-sekolah/profil',
        ];

        foreach ($cases as $key => $url) {
            $user = UserBaru::find($this->ids[$key]);
            $this->assertNotNull($user, 'Prekondisi: user '.$key.' harus ada.');
            $res = $this->actingAs($user)->get($url);
            $res->assertStatus(200);
            $this->assertToggleCoverage($res->getContent(), ['current_password', 'password', 'password_confirmation']);
        }
    }
}
