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
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => $name, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };
        // Akun tanpa relasi agar dapat dihapus aman (tanpa orphan).
        $stale = $mkUser('f99_stale', 'Stale F99', 'admin');
        $other = $mkUser('f99_other', 'Other F99', 'admin');

        $this->ids = ['stale' => $stale->id, 'other' => $other->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                UserBaru::whereIn('id', [$this->ids['stale'] ?? 0, $this->ids['other'] ?? 0])->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    public function test_1_valid_login_and_refresh_stays_logged_in(): void
    {
        $this->post('/login', ['username' => 'f99_stale', 'password' => 'password123'])->assertRedirect('/admin');
        $this->get('/admin')->assertStatus(200);
        $this->get('/admin')->assertStatus(200);
        $this->assertAuthenticated();
    }

    public function test_2_deleted_account_auto_logout_no_loop(): void
    {
        $stale = UserBaru::findOrFail($this->ids['stale']);
        $this->actingAs($stale)->get('/admin')->assertStatus(200);
        // Hapus akun langsung dari database selagi sesi aktif.
        $stale->delete();
        // Refresh halaman terproteksi: tidak stuck/error/loop, kembali ke login.
        $res = $this->get('/admin');
        $res->assertRedirect(route('login'));
        $this->assertGuest();
        // Tanpa loop: halaman login tampil normal.
        $this->followRedirects($res)->assertStatus(200)->assertSee('Masuk', false);
    }

    public function test_3_login_with_other_valid_account(): void
    {
        UserBaru::whereKey($this->ids['stale'])->delete();
        $this->post('/login', ['username' => 'f99_other', 'password' => 'password123'])->assertRedirect('/admin');
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
}
