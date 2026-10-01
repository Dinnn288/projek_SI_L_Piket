<?php

namespace Tests\Feature;

use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileFaseTest extends TestCase
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
        $admin = $mkUser('fpr_admin', 'Admin FPR', 'admin');
        $siswa = $mkUser('fpr_siswa', 'Siswa FPR', 'siswa');
        $guru = $mkUser('fpr_guru', 'Guru FPR', 'guru');
        $kepsek = $mkUser('fpr_kepsek', 'Kepsek FPR', 'kepala_sekolah');

        $this->ids = ['admin' => $admin->id, 'siswa' => $siswa->id, 'guru' => $guru->id, 'kepsek' => $kepsek->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                UserBaru::whereIn('id', [$this->ids['admin'] ?? 0, $this->ids['siswa'] ?? 0, $this->ids['guru'] ?? 0, $this->ids['kepsek'] ?? 0])->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    public function test_profile_pages_per_role(): void
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
            $res->assertSee($user->username, false);
            $res->assertDontSee('password123', false);
        }
        $this->refreshApplication();
        $this->reconnect();
        $this->get('/admin/profil')->assertRedirect('/login');
    }

    public function test_cross_role_profile_blocked(): void
    {
        $siswa = UserBaru::find($this->ids['siswa']);
        $guru = UserBaru::find($this->ids['guru']);
        $kepsek = UserBaru::find($this->ids['kepsek']);
        $admin = UserBaru::find($this->ids['admin']);
        $this->actingAs($siswa)->get('/admin/profil')->assertStatus(403);
        $this->actingAs($guru)->get('/siswa/profil')->assertStatus(403);
        $this->actingAs($kepsek)->get('/guru/profil')->assertStatus(403);
        $this->actingAs($admin)->get('/kepala-sekolah/profil')->assertStatus(403);
    }

    public function test_password_change_success_and_login(): void
    {
        $siswa = UserBaru::find($this->ids['siswa']);
        $this->actingAs($siswa)->put('/siswa/profil/password', [
            'current_password' => 'password123',
            'password' => 'baru12345',
            'password_confirmation' => 'baru12345',
        ])->assertSessionHas('success');
        $this->assertTrue(Hash::check('baru12345', $siswa->fresh()->password));
        $this->refreshApplication();
        $this->reconnect();
        $this->post('/login', ['username' => 'fpr_siswa', 'password' => 'baru12345'])->assertRedirect('/siswa');
    }

    public function test_password_change_validation(): void
    {
        $guru = UserBaru::find($this->ids['guru']);
        // Password lama salah
        $this->actingAs($guru)->put('/guru/profil/password', [
            'current_password' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('password123', $guru->fresh()->password));
        // Konfirmasi beda
        $this->actingAs($guru)->put('/guru/profil/password', [
            'current_password' => 'password123', 'password' => 'baru12345', 'password_confirmation' => 'beda',
        ])->assertSessionHasErrors('password');
        // Sama dengan lama
        $this->actingAs($guru)->put('/guru/profil/password', [
            'current_password' => 'password123', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password123', $guru->fresh()->password));
        // Guest ditolak
        $this->refreshApplication();
        $this->reconnect();
        $this->put('/guru/profil/password', ['current_password' => 'x'])->assertRedirect('/login');
    }

    public function test_cannot_change_other_user_password(): void
    {
        // Endpoint selalu memakai Auth::user(); tidak ada parameter ID target.
        // Buktikan password admin tidak berubah saat siswa mengganti miliknya.
        $siswa = UserBaru::find($this->ids['siswa']);
        $adminBefore = UserBaru::find($this->ids['admin'])->password;
        $this->actingAs($siswa)->put('/siswa/profil/password', [
            'current_password' => 'password123', 'password' => 'milik12345', 'password_confirmation' => 'milik12345',
        ])->assertSessionHas('success');
        $this->assertEquals($adminBefore, UserBaru::find($this->ids['admin'])->password);
    }
}
