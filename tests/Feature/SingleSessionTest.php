<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Services\GuruDutyService;
use App\Support\SingleSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * FIRST LOGIN WINS: satu akun = satu sesi aktif.
 *
 * - Login pertama berhasil dan menjadi sesi aktif.
 * - Login kedua dengan akun yang sama DITOLAK (tetap di login + pesan),
 *   marker TIDAK di-overwrite, Device A tetap aktif.
 * - Setelah Device A logout normal, Device B boleh login.
 * - Marker basi (session expired/hilang) tidak mengunci akun selamanya.
 */
class SingleSessionTest extends TestCase
{
    private array $userIds = [];

    private array $sessionIds = [];

    private function reconnect(): void
    {
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        config(['database.connections.mysql.database' => 'si_l_piket']);
        DB::reconnect('mysql');
    }

    /** Aplikasi baru yang berbagi store persisten (DB+cache) = satu device baru. */
    private function freshDevice(): void
    {
        $this->refreshApplication();
        $this->reconnect();
        config(['session.driver' => 'database']);
        config(['cache.default' => 'database']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reconnect();
        config(['session.driver' => 'database']);
        config(['cache.default' => 'database']);

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_SS'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_SS')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_SS', 'status' => 'aktif']);

        $users = [
            'ss_admin' => ['Admin SS', 'admin'],
            'ss_siswa' => ['Siswa SS', 'siswa'],
            'ss_guru' => ['Guru SS', 'guru'],
            'ss_kepsek' => ['Kepsek SS', 'kepala_sekolah'],
        ];

        foreach ($users as $u => [$name, $role]) {
            $user = UserBaru::where('username', $u)->first();

            if ($user) {
                $user->update(['name' => $name, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
                $user = $user->fresh();
            } else {
                $user = UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
            }

            $this->userIds[$u] = $user->id;
            Cache::forget(SingleSession::key($user->id));
        }

        $siswa = UserBaru::where('username', 'ss_siswa')->firstOrFail();
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'SSNIS001', 'class_id' => $class->id]);
        }
        $guru = UserBaru::where('username', 'ss_guru')->firstOrFail();
        $teacher = Teacher::where('user_id', $guru->id)->first()
            ?: Teacher::create(['user_id' => $guru->id, 'nip' => 'SSNIP001']);
        $today = GuruDutyService::todayDay() ?? 'senin';
        if (! \App\Models\DutySchedule::where('teacher_id', $teacher->id)->where('day', $today)->exists()) {
            \App\Models\DutySchedule::create(['teacher_id' => $teacher->id, 'day' => $today]);
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            config(['cache.default' => 'database']);
            if (! empty($this->sessionIds)) {
                DB::table('sessions')->whereIn('id', $this->sessionIds)->delete();
            }
            foreach (array_values($this->userIds) as $id) {
                Cache::forget(SingleSession::key($id));
            }
            Student::where('nis', 'SSNIS001')->delete();
            $gids = UserBaru::whereIn('username', ['ss_guru'])->pluck('id');
            $tids = Teacher::whereIn('user_id', $gids)->pluck('id');
            if ($tids->isNotEmpty()) {
                \App\Models\DutySchedule::whereIn('teacher_id', $tids)->delete();
            }
            Teacher::whereIn('user_id', $gids)->delete();
            UserBaru::whereIn('username', ['ss_admin', 'ss_siswa', 'ss_guru', 'ss_kepsek'])->delete();
            SchoolClass::where('name', 'TEST_KELAS_SS')->delete();
            Major::where('name', 'TEST_MAJOR_SS')->delete();
        } catch (\Throwable $e) {
        }
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function cookieName(): string
    {
        return (string) config('session.cookie');
    }

    private function clearCookies(): void
    {
        foreach (['defaultCookies', 'unencryptedCookies'] as $prop) {
            $ref = new \ReflectionProperty($this, $prop);
            $ref->setAccessible(true);
            $ref->setValue($this, []);
        }
    }

    private function dashboardFor(string $username): string
    {
        return match ($username) {
            'ss_admin' => '/admin',
            'ss_siswa' => '/siswa',
            'ss_guru' => '/guru',
            'ss_kepsek' => '/kepala-sekolah',
        };
    }

    /**
     * Login nyata via POST dari device baru. Mengembalikan nilai mentah
     * cookie session dari response untuk diputar ulang sebagai cookie
     * device tersebut (mensimulasikan cookie jar browser).
     */
    private function loginDevice(string $username, string $expectedRedirect): string
    {
        $this->freshDevice();
        $res = $this->post('/login', ['username' => $username, 'password' => 'password123']);
        $res->assertRedirect($expectedRedirect);

        $cookie = collect($res->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $this->cookieName());
        $this->assertNotNull($cookie, 'Login harus mengatur session cookie.');

        return $cookie->getValue();
    }

    private function loginDeviceRejected(string $username): void
    {
        $this->freshDevice();
        // from('/login') agar back() kembali ke halaman login (satu hop).
        $res = $this->from('/login')->post('/login', ['username' => $username, 'password' => 'password123']);
        $res->assertRedirect('/login');
        $res->assertSessionHasErrors('username');
        $this->assertGuest();

        // Bawa session cookie hasil POST (sudah di-invalidate + regenerate
        // di controller) agar flashed error ikut ter-render di halaman login,
        // seperti browser yang mengikuti redirect dengan cookie baru.
        $cookie = collect($res->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $this->cookieName());
        $this->assertNotNull($cookie, 'Penolakan login harus mengatur session cookie.');
        $this->withUnencryptedCookie($this->cookieName(), $cookie->getValue());

        try {
            $page = $this->get('/login');
            $page->assertStatus(200);
            $page->assertSee('perangkat lain', false);
        } finally {
            $this->clearCookies();
        }
    }

    private function markerOf(string $username): ?string
    {
        $uid = UserBaru::where('username', $username)->value('id');

        return Cache::get(SingleSession::key($uid));
    }

    private function activeSessionId(string $username): string
    {
        $sid = $this->markerOf($username);
        $this->assertNotEmpty($sid, 'Login harus mencatat marker sesi aktif.');
        $this->sessionIds[] = $sid;

        return $sid;
    }

    private function getWithCookie(string $url, string $rawCookie, bool $fresh = true)
    {
        if ($fresh) {
            $this->freshDevice();
        }
        $this->withUnencryptedCookie($this->cookieName(), $rawCookie);

        try {
            return $this->get($url);
        } finally {
            $this->clearCookies();
        }
    }

    private function postWithCookie(string $url, string $rawCookie, array $data = [], bool $fresh = true)
    {
        if ($fresh) {
            $this->freshDevice();
        }
        $this->withUnencryptedCookie($this->cookieName(), $rawCookie);

        try {
            return $this->post($url, $data);
        } finally {
            $this->clearCookies();
        }
    }

    public function test_1_first_login_wins_second_login_rejected(): void
    {
        // Test 1: login pertama berhasil.
        $cA = $this->loginDevice('ss_admin', '/admin');
        $sA = $this->activeSessionId('ss_admin');

        // Test 2: login kedua dengan akun sama DITOLAK + pesan jelas.
        $this->loginDeviceRejected('ss_admin');

        // Test 4: marker Device A tidak ditimpa oleh Device B.
        $this->assertEquals($sA, $this->markerOf('ss_admin'));

        // Test 3: Device A tetap aktif (dashboard tetap 200).
        $this->getWithCookie('/admin', $cA)->assertStatus(200);
        $this->getWithCookie('/admin', $cA)->assertStatus(200);
    }

    public function test_2_logout_frees_account_for_next_login(): void
    {
        // Test 5: A login -> B ditolak -> A logout -> B boleh login.
        $cA = $this->loginDevice('ss_admin', '/admin');
        $this->activeSessionId('ss_admin');

        $this->loginDeviceRejected('ss_admin');

        $this->postWithCookie('/logout', $cA)->assertRedirect('/login');
        $this->assertNull($this->markerOf('ss_admin'), 'Logout normal harus menghapus marker sesi aktif.');

        $cB = $this->loginDevice('ss_admin', '/admin');
        $this->activeSessionId('ss_admin');
        $this->getWithCookie('/admin', $cB)->assertStatus(200);

        // Sesi lama A yang sudah logout tidak bisa dipakai kembali.
        $this->getWithCookie('/admin', $cA)->assertRedirect(route('login'));
    }

    public function test_3_expired_session_allows_new_login(): void
    {
        // Test 6: sesi Device A yang sudah tidak valid -> Device B boleh login.
        $cA = $this->loginDevice('ss_admin', '/admin');
        $sA = $this->activeSessionId('ss_admin');

        // Simulasikan session expired: baris session hilang + last_activity basi.
        $this->reconnect();
        DB::table('sessions')->where('id', $sA)->delete();
        Cache::forget(SingleSession::key(UserBaru::where('username', 'ss_admin')->value('id')));
        SingleSession::remember(UserBaru::where('username', 'ss_admin')->value('id'), $sA);
        DB::table('sessions')->insert([
            'id' => $sA,
            'user_id' => UserBaru::where('username', 'ss_admin')->value('id'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode(''),
            'last_activity' => now()->subMinutes(SingleSession::ttlMinutes() + 30)->getTimestamp(),
        ]);

        $cB = $this->loginDevice('ss_admin', '/admin');
        $sB = $this->activeSessionId('ss_admin');
        $this->assertNotEquals($sA, $sB);
        $this->getWithCookie('/admin', $cB)->assertStatus(200);

        // Cookie lama A tidak berlaku lagi.
        $this->getWithCookie('/admin', $cA)->assertRedirect(route('login'));
    }

    public function test_4_all_roles_first_login_wins(): void
    {
        // Test 7: mekanisme berlaku untuk seluruh role.
        foreach (['ss_admin', 'ss_siswa', 'ss_guru', 'ss_kepsek'] as $username) {
            $dest = $this->dashboardFor($username);
            $cA = $this->loginDevice($username, $dest);
            $sA = $this->activeSessionId($username);

            $this->loginDeviceRejected($username);
            $this->assertEquals($sA, $this->markerOf($username), 'Marker '.$username.' tidak boleh ditimpa.');
            $this->getWithCookie($dest, $cA)->assertStatus(200);

            $this->postWithCookie('/logout', $cA)->assertRedirect('/login');
            $this->assertNull($this->markerOf($username), 'Logout '.$username.' harus membebaskan akun.');
            $cB = $this->loginDevice($username, $dest);
            $this->getWithCookie($dest, $cB)->assertStatus(200);
            $this->postWithCookie('/logout', $cB)->assertRedirect('/login');
        }
    }

    public function test_5_different_accounts_independent(): void
    {
        $cA = $this->loginDevice('ss_admin', '/admin');
        $cG = $this->loginDevice('ss_guru', '/guru');

        $this->getWithCookie('/admin', $cA)->assertStatus(200);
        $this->getWithCookie('/guru', $cG)->assertStatus(200);

        $this->postWithCookie('/logout', $cA)->assertRedirect('/login');
        $this->postWithCookie('/logout', $cG)->assertRedirect('/login');
    }

    public function test_6_role_authorization_intact(): void
    {
        $cG = $this->loginDevice('ss_guru', '/guru');
        // Session valid tetapi role guru: tetap 403 di area admin.
        $this->getWithCookie('/admin', $cG)->assertStatus(403);
        // Dan tetap bisa membuka area guru.
        $this->getWithCookie('/guru', $cG)->assertStatus(200);
        $this->postWithCookie('/logout', $cG)->assertRedirect('/login');
    }
}
