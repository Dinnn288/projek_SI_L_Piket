<?php

namespace Tests\Feature;

use App\Models\UserBaru;
use App\Support\SingleSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

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

        foreach (['ss_a' => 'admin', 'ss_b' => 'admin', 'ss_g' => 'guru'] as $u => $role) {
            $user = UserBaru::where('username', $u)->first();

            if ($user) {
                $user->update(['name' => 'SS '.strtoupper($u), 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
                $user = $user->fresh();
            } else {
                $user = UserBaru::create(['name' => 'SS '.strtoupper($u), 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
            }

            $this->userIds[$u] = $user->id;
            Cache::forget(SingleSession::key($user->id));
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
            UserBaru::whereIn('username', ['ss_a', 'ss_b', 'ss_g'])->delete();
        } catch (\Throwable $e) {
        }
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

    private function activeSessionId(string $username): string
    {
        $uid = UserBaru::where('username', $username)->value('id');
        $sid = Cache::get(SingleSession::key($uid));
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

    public function test_1_single_session_old_device_rejected(): void
    {
        $cA = $this->loginDevice('ss_a', '/admin');
        $sA = $this->activeSessionId('ss_a');

        $cB = $this->loginDevice('ss_a', '/admin');
        $sB = $this->activeSessionId('ss_a');
        $this->assertNotEquals($sA, $sB);

        // Device A (sesi lama): ditolak, logout, redirect login + pesan.
        $res = $this->getWithCookie('/admin', $cA);
        $res->assertRedirect(route('login'));
        $res->assertSessionHasErrors('username');
        $this->assertGuest();

        // Device B (sesi terbaru): tetap valid.
        $this->getWithCookie('/admin', $cB)->assertStatus(200);
    }

    public function test_2_different_accounts_independent(): void
    {
        $cA = $this->loginDevice('ss_a', '/admin');
        $cB = $this->loginDevice('ss_b', '/admin');

        $this->getWithCookie('/admin', $cA)->assertStatus(200);
        $this->getWithCookie('/admin', $cB)->assertStatus(200);
    }

    public function test_3_multiple_logins_only_latest_valid(): void
    {
        $c1 = $this->loginDevice('ss_a', '/admin');
        $c2 = $this->loginDevice('ss_a', '/admin');
        $c3 = $this->loginDevice('ss_a', '/admin');

        $this->getWithCookie('/admin', $c1)->assertRedirect(route('login'));
        $this->getWithCookie('/admin', $c2)->assertRedirect(route('login'));
        $this->getWithCookie('/admin', $c3)->assertStatus(200);
    }

    public function test_4_stale_deleted_user_still_redirects_login(): void
    {
        $cA = $this->loginDevice('ss_a', '/admin');
        UserBaru::whereKey($this->userIds['ss_a'])->delete();

        // Tanpa refresh aplikasi di tengah: cookie yang sama dipakai pada
        // aplikasi yang sama tempat login terjadi.
        $res = $this->getWithCookie('/admin', $cA, false);
        $res->assertRedirect(route('login'));
        $res->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_5_role_authorization_intact(): void
    {
        $cG = $this->loginDevice('ss_g', '/guru');
        // Session valid tetapi role guru: tetap 403 di area admin.
        $this->getWithCookie('/admin', $cG)->assertStatus(403);
        // Dan tetap bisa membuka area guru.
        $this->getWithCookie('/guru', $cG)->assertStatus(200);
    }

    public function test_6_post_with_stale_session_rejected(): void
    {
        $cA = $this->loginDevice('ss_a', '/admin');
        $cB = $this->loginDevice('ss_a', '/admin');

        // Sesi lama mencoba aksi POST protected: ditolak sebelum action.
        $res = $this->postWithCookie('/logout', $cA);
        $res->assertRedirect(route('login'));
        $res->assertSessionHasErrors('username');
        $this->assertGuest();

        // Sesi terbaru logout normal tanpa error sesi.
        $ok = $this->postWithCookie('/logout', $cB);
        $ok->assertRedirect('/login');
        $ok->assertSessionMissing('errors');
        $this->assertGuest();
    }
}
