<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\UserBaru;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationRetentionTest extends TestCase
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
        $siswa = $mkUser('frt_siswa', 'Siswa FRT', 'siswa');
        $admin = $mkUser('frt_admin', 'Admin FRT', 'admin');

        // Dashboard siswa butuh record students (proteksi existing).
        $classId = \App\Models\SchoolClass::value('id');

        if ($classId && ! \App\Models\Student::where('user_id', $siswa->id)->exists()) {
            \App\Models\Student::create(['user_id' => $siswa->id, 'nis' => 'FRTNIS001', 'class_id' => $classId]);
        }

        $this->ids = ['siswa' => $siswa->id, 'admin' => $admin->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                Notification::whereIn('user_id', [$this->ids['siswa'] ?? 0, $this->ids['admin'] ?? 0])->delete();
                \App\Models\Student::where('nis', 'FRTNIS001')->delete();
                UserBaru::whereIn('id', [$this->ids['siswa'] ?? 0, $this->ids['admin'] ?? 0])->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function makeNotif(int $userId, string $title, int $daysOld): Notification
    {
        $n = Notification::create([
            'user_id' => $userId, 'report_id' => null,
            'title' => $title, 'message' => 'M', 'is_read' => false,
        ]);
        $n->created_at = Carbon::now()->subDays($daysOld);
        $n->save();

        return $n->fresh();
    }

    public function test_preview_max_3_latest_first_and_isolation(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswa']);
        $admin = UserBaru::findOrFail($this->ids['admin']);
        $base = Carbon::now()->subMinutes(10);
        for ($i = 1; $i <= 5; $i++) {
            $n = $this->makeNotif($siswa->id, 'NP'.$i, 0);
            // Timestamp eksplisit agar urutan latest() deterministik.
            $n->created_at = (clone $base)->addSeconds($i * 10);
            $n->save();
        }
        $this->makeNotif($admin->id, 'ADMIN1', 0);

        $res = $this->actingAs($siswa)->get('/siswa');
        $res->assertStatus(200);
        // Maksimal 3 terbaru, urutan terbaru dulu
        foreach (['NP5', 'NP4', 'NP3'] as $t) {
            $res->assertSee($t, false);
        }
        $res->assertDontSee('NP2', false);
        $res->assertDontSee('NP1', false);
        // Isolasi: notifikasi admin tidak muncul untuk siswa
        $res->assertDontSee('ADMIN1', false);
    }

    public function test_cutoff_30_days_and_cleanup_idempotent(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswa']);
        // Bekukan waktu agar cutoff identik antara pembuatan dan cleanup.
        Carbon::setTestNow(Carbon::now());
        $n29 = $this->makeNotif($siswa->id, 'D29', 29);
        $n30 = $this->makeNotif($siswa->id, 'D30', 30);
        $n31 = $this->makeNotif($siswa->id, 'D31', 31);
        $n60 = $this->makeNotif($siswa->id, 'D60', 60);

        // Cutoff konsisten: created_at < now-30d terhapus.
        $cutoff = Carbon::now()->subDays(30);
        $this->assertTrue($n29->created_at->greaterThanOrEqualTo($cutoff->copy()->subDay()));
        $this->assertTrue($n31->created_at->lessThan($cutoff));
        $this->assertTrue($n60->created_at->lessThan($cutoff));

        Artisan::call('notifications:cleanup');
        $out = Artisan::output();
        $this->assertStringContainsString('Cleanup selesai', $out);

        $this->assertNotNull(Notification::find($n29->id));
        $this->assertNotNull(Notification::find($n30->id));
        $this->assertNull(Notification::find($n31->id));
        $this->assertNull(Notification::find($n60->id));

        // Idempotent: jalankan kedua kali tanpa error, tidak ada yang terhapus lagi.
        Artisan::call('notifications:cleanup');
        $this->assertNotNull(Notification::find($n29->id));
        $this->assertNotNull(Notification::find($n30->id));
        Carbon::setTestNow();
    }

    public function test_dropdown_routes_and_sidebar_unchanged(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswa']);
        $admin = UserBaru::findOrFail($this->ids['admin']);
        $guru = UserBaru::where('role', 'guru')->where('status', 'aktif')->first();
        $kepsek = UserBaru::where('role', 'kepala_sekolah')->where('status', 'aktif')->first();

        foreach ([
            [$admin, '/admin'],
            [$siswa, '/siswa'],
        ] as [$user, $url]) {
            $res = $this->actingAs($user)->get($url);
            $res->assertStatus(200);
            // Dropdown profil tetap berisi Profil, Notifikasi, Logout
            $res->assertSee('data-sl-profile', false);
        }

        if ($guru) {
            $this->actingAs($guru)->get('/guru')->assertStatus(200);
        }

        if ($kepsek) {
            $this->actingAs($kepsek)->get('/kepala-sekolah')->assertStatus(200);
        }

        // Sidebar tetap tanpa Profil/Notifikasi sebagai menu
        foreach (['admin', 'siswa', 'guru', 'kepala-sekolah'] as $layout) {
            $html = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));
            $this->assertDoesNotMatchRegularExpression('/sl-link[^>]*>[^<]*(Profil|Notifikasi)/', $html);
        }
    }
}
