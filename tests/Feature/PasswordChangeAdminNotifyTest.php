<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeAdminNotifyTest extends TestCase
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
        $admin = $mkUser('fpn_admin', 'Admin FPN', 'admin');
        $siswa = $mkUser('fpn_siswa', 'Siswa FPN', 'siswa');
        $guru = $mkUser('fpn_guru', 'Guru FPN', 'guru');
        $kepsek = $mkUser('fpn_kepsek', 'Kepsek FPN', 'kepala_sekolah');

        $this->ids = ['admin' => $admin->id, 'siswa' => $siswa->id, 'guru' => $guru->id, 'kepsek' => $kepsek->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                Notification::whereIn('user_id', array_values($this->ids))->delete();
                UserBaru::whereIn('id', array_values($this->ids))->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function changePassword(string $username, string $current, string $new, string $url): \Illuminate\Testing\TestResponse
    {
        $user = UserBaru::where('username', $username)->firstOrFail();

        return $this->actingAs($user)->put($url, [
            'current_password' => $current,
            'password' => $new,
            'password_confirmation' => $new,
        ]);
    }

    public function test_each_role_notifies_admin(): void
    {
        $cases = [
            ['fpn_siswa', 'Siswa FPN', '/siswa/profil/password'],
            ['fpn_guru', 'Guru FPN', '/guru/profil/password'],
            ['fpn_kepsek', 'Kepsek FPN', '/kepala-sekolah/profil/password'],
            ['fpn_admin', 'Admin FPN', '/admin/profil/password'],
        ];
        foreach ($cases as [$username, $name, $url]) {
            $this->changePassword($username, 'password123', 'baru12345', $url)->assertSessionHas('success');
            $notif = Notification::where('user_id', $this->ids['admin'])
                ->where('title', 'Perubahan Password')
                ->where('message', 'like', 'Akun '.$name.' mengubah password pada%')
                ->first();
            $this->assertNotNull($notif, "Notifikasi untuk $username harus ada.");
            $this->assertFalse((bool) $notif->is_read);
            $this->assertNull($notif->report_id);
            $this->assertStringNotContainsString('password123', $notif->message);
            $this->assertStringNotContainsString('baru12345', $notif->message);
            // Kembalikan password agar iterasi deterministik
            $user = UserBaru::where('username', $username)->firstOrFail();
            $user->password = Hash::make('password123');
            $user->save();
        }
        $this->assertEquals(4, Notification::where('user_id', $this->ids['admin'])->where('title', 'Perubahan Password')->count());
    }

    public function test_failed_change_creates_no_notification(): void
    {
        $before = Notification::where('user_id', $this->ids['admin'])->where('title', 'Perubahan Password')->count();
        $siswa = UserBaru::findOrFail($this->ids['siswa']);
        // Password lama salah
        $this->actingAs($siswa)->put('/siswa/profil/password', [
            'current_password' => 'salah', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHasErrors('current_password');
        // Konfirmasi beda
        $this->actingAs($siswa)->put('/siswa/profil/password', [
            'current_password' => 'password123', 'password' => 'baru12345', 'password_confirmation' => 'beda',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password123', $siswa->fresh()->password));
        $this->assertEquals($before, Notification::where('user_id', $this->ids['admin'])->where('title', 'Perubahan Password')->count());
    }

    public function test_no_duplicate_and_admin_badge(): void
    {
        $guru = UserBaru::findOrFail($this->ids['guru']);
        $this->actingAs($guru)->put('/guru/profil/password', [
            'current_password' => 'password123', 'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertSessionHas('success');
        $count = Notification::where('user_id', $this->ids['admin'])->where('title', 'Perubahan Password')->count();
        $this->assertEquals(1, $count);
        // Halaman notifikasi admin menampilkan + badge unread
        $admin = UserBaru::findOrFail($this->ids['admin']);
        $res = $this->actingAs($admin)->get('/admin/notifikasi');
        $res->assertStatus(200);
        $res->assertSee('Perubahan Password', false);
        $res->assertSee('Guru FPN', false);
    }
}
