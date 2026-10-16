<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationReadUnreadTest extends TestCase
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
        $admin = $mkUser('fnt_admin', 'Admin FNT', 'admin');
        $siswaA = $mkUser('fnt_siswa_a', 'Siswa A FNT', 'siswa');
        $siswaB = $mkUser('fnt_siswa_b', 'Siswa B FNT', 'siswa');
        $guru = $mkUser('fnt_guru', 'Guru FNT', 'guru');
        $kepsek = $mkUser('fnt_kepsek', 'Kepsek FNT', 'kepala_sekolah');

        $this->ids = [
            'admin' => $admin->id, 'siswaA' => $siswaA->id, 'siswaB' => $siswaB->id,
            'guru' => $guru->id, 'kepsek' => $kepsek->id,
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                Notification::whereIn('user_id', [$this->ids['siswaA'] ?? 0, $this->ids['siswaB'] ?? 0, $this->ids['guru'] ?? 0, $this->ids['kepsek'] ?? 0, $this->ids['admin'] ?? 0])->delete();
                UserBaru::whereIn('id', [$this->ids['admin'] ?? 0, $this->ids['siswaA'] ?? 0, $this->ids['siswaB'] ?? 0, $this->ids['guru'] ?? 0, $this->ids['kepsek'] ?? 0])->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    public function test_unread_badge_and_read_flow_per_user(): void
    {
        $a = UserBaru::findOrFail($this->ids['siswaA']);
        $b = UserBaru::findOrFail($this->ids['siswaB']);
        Notification::create(['user_id' => $a->id, 'report_id' => null, 'title' => 'N1', 'message' => 'M1', 'is_read' => false]);
        Notification::create(['user_id' => $a->id, 'report_id' => null, 'title' => 'N2', 'message' => 'M2', 'is_read' => false]);
        Notification::create(['user_id' => $b->id, 'report_id' => null, 'title' => 'N3', 'message' => 'M3', 'is_read' => false]);

        // Halaman notifikasi A: 2 item, badge 2
        $res = $this->actingAs($a)->get('/siswa/notifikasi');
        $res->assertStatus(200);
        $res->assertSee('N1', false);
        $res->assertSee('N2', false);
        $res->assertDontSee('N3', false);
        // Klik N1 -> read + badge berkurang, milik B tetap unread
        $n1 = Notification::where('user_id', $a->id)->where('title', 'N1')->firstOrFail();
        $this->actingAs($a)->post('/siswa/notifikasi/'.$n1->id.'/read')->assertRedirect(route('siswa.dashboard'));
        $this->assertTrue((bool) $n1->fresh()->is_read);
        $this->assertEquals(1, Notification::where('user_id', $a->id)->where('is_read', false)->count());
        $this->assertEquals(1, Notification::where('user_id', $b->id)->where('is_read', false)->count());
        $res2 = $this->actingAs($a)->get('/siswa/notifikasi');
        $res2->assertStatus(200);
        $res2->assertSee('Baru', false);
    }

    public function test_idor_and_cross_role_blocked(): void
    {
        $a = UserBaru::findOrFail($this->ids['siswaA']);
        $b = UserBaru::findOrFail($this->ids['siswaB']);
        $guru = UserBaru::findOrFail($this->ids['guru']);
        $n = Notification::create(['user_id' => $b->id, 'report_id' => null, 'title' => 'NX', 'message' => 'MX', 'is_read' => false]);
        // A tidak bisa read milik B
        $this->actingAs($a)->post('/siswa/notifikasi/'.$n->id.'/read')->assertStatus(403);
        $this->assertFalse((bool) $n->fresh()->is_read);
        // Guru tidak bisa pakai route siswa
        $this->actingAs($guru)->get('/siswa/notifikasi')->assertStatus(403);
        $this->actingAs($guru)->post('/siswa/notifikasi/'.$n->id.'/read')->assertStatus(403);
        // Admin/guru/kepsek halaman sendiri 200
        $this->actingAs(UserBaru::findOrFail($this->ids['admin']))->get('/admin/notifikasi')->assertStatus(200);
        $this->actingAs($guru)->get('/guru/notifikasi')->assertStatus(200);
        $this->actingAs(UserBaru::findOrFail($this->ids['kepsek']))->get('/kepala-sekolah/notifikasi')->assertStatus(200);
        // Guest ditolak
        $this->refreshApplication();
        $this->reconnect();
        $this->get('/siswa/notifikasi')->assertRedirect('/login');
    }

    public function test_pagination_and_all_read_hides_badge_count(): void
    {
        $a = UserBaru::findOrFail($this->ids['siswaA']);
        for ($i = 1; $i <= 12; $i++) {
            Notification::create(['user_id' => $a->id, 'report_id' => null, 'title' => 'NP'.$i, 'message' => 'M', 'is_read' => false]);
        }
        $p1 = $this->actingAs($a)->get('/siswa/notifikasi');
        $p1->assertStatus(200);
        $p1->assertSee('Menampilkan 1–10 dari 12', false);
        $p2 = $this->actingAs($a)->get('/siswa/notifikasi?page=2');
        $p2->assertStatus(200);
        $p2->assertSee('Menampilkan 11–12 dari 12', false);
        // Tandai semua read -> badge hilang (count 0)
        Notification::where('user_id', $a->id)->update(['is_read' => true]);
        $this->assertEquals(0, Notification::where('user_id', $a->id)->where('is_read', false)->count());
    }
}
