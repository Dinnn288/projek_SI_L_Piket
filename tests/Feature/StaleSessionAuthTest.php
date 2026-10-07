<?php

namespace Tests\Feature;

use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaleSessionAuthTest extends TestCase
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

        $mkUser = function (string $u, string $name, string $role) {
            $existing = UserBaru::where('username', $u)->first();

            if ($existing) {
                // Reset deterministik: sisa run sebelumnya mungkin nonaktif.
                $existing->update(['name' => $name, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);

                return $existing->fresh();
            }

            return UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        // Akun tanpa relasi agar dapat dihapus aman (tanpa orphan).
        $stale = $mkUser('f99_stale', 'Stale F99', 'admin');
        $other = $mkUser('f99_other', 'Other F99', 'admin');
        // Role admin agar halaman uji (/admin) tidak memerlukan profil
        // students/teachers; fokus test adalah status nonaktif.
        $deact = $mkUser('f99_deact', 'Deact F99', 'admin');

        $this->ids = ['stale' => $stale->id, 'other' => $other->id, 'deact' => $deact->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                UserBaru::whereIn('id', [$this->ids['stale'] ?? 0, $this->ids['other'] ?? 0, $this->ids['deact'] ?? 0])->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function loginWithCookie(string $username): void
    {
        $res = $this->post('/login', ['username' => $username, 'password' => 'password123']);
        $res->assertRedirect('/admin');
        $cookie = collect($res->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));
        $this->assertNotNull($cookie, 'Login harus mengatur session cookie.');
        $this->withUnencryptedCookie(config('session.cookie'), $cookie->getValue());
    }

    public function test_1_valid_login_and_refresh_stays_logged_in(): void
    {
        $this->loginWithCookie('f99_stale');
        $this->get('/admin')->assertStatus(200);
        $this->get('/admin')->assertStatus(200);
        $this->assertAuthenticated();
    }

    public function test_2_deleted_account_auto_logout_no_loop(): void
    {
        $stale = UserBaru::findOrFail($this->ids['stale']);
        $this->actingAs($stale)->get('/admin')->assertStatus(200);
        $stale->delete();
        $res = $this->get('/admin');
        $res->assertRedirect(route('login'));
        $this->assertGuest();
        $followed = $this->followRedirects($res);
        $followed->assertStatus(200);
        $followed->assertSee('sudah tidak tersedia', false);
    }

    public function test_3_login_with_other_valid_account(): void
    {
        UserBaru::whereKey($this->ids['stale'])->delete();
        $this->loginWithCookie('f99_other');
        $this->get('/admin')->assertStatus(200);
    }

    public function test_4_wrong_credentials_stay_on_login(): void
    {
        $this->post('/login', ['username' => 'f99_other', 'password' => 'salah'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => 'tidak_ada_f99', 'password' => 'x'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_5_logout_then_dashboard_redirects_login(): void
    {
        $other = UserBaru::findOrFail($this->ids['other']);
        $this->actingAs($other)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/siswa')->assertRedirect(route('login'));
    }

    public function test_6_deactivated_account_auto_logout_with_message(): void
    {
        $deact = UserBaru::findOrFail($this->ids['deact']);
        $this->actingAs($deact)->get('/admin')->assertStatus(200);
        $deact->update(['status' => 'nonaktif']);
        $res = $this->get('/admin');
        $res->assertRedirect(route('login'));
        $this->assertGuest();
        $followed = $this->followRedirects($res);
        $followed->assertStatus(200);
        $followed->assertSee('dinonaktifkan', false);
    }

    public function test_7_role_violation_still_403(): void
    {
        // Akun siswa aktif (tanpa profil tidak masalah untuk authorization
        // 403 yang terjadi sebelum controller) mengakses area admin.
        $siswa = UserBaru::create(['name' => 'Siswa F99X', 'username' => 'f99_siswax', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);
        $this->actingAs($siswa)->get('/admin')->assertStatus(403);
        $siswa->delete();
    }
}
