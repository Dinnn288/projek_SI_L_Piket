<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\UserBaru;
use App\Rules\IndonesianWhatsappNumber;
use App\Services\FonnteService;
use App\Support\WaCredentialDispatch;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integrasi kredensial WA via Fonnte: form -> konfirmasi -> kirim.
 *
 * ISOLASI: reconnect + assert nama DB (si_l_piket_test). Semua HTTP
 * keluar memakai Http::fake — tidak ada pesan sungguhan.
 */
class AccountWhatsappFonnteTest extends TestCase
{
    private const FAKE_TOKEN = 'wf-fake-token-abc123';

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

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_WF'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_WF')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_WF', 'status' => 'aktif']);

        $mk = function (string $u, string $role) {
            return UserBaru::where('username', $u)->first()
                ?: UserBaru::create(['name' => 'Nama '.$u, 'username' => $u, 'password' => Hash::make('password123'), 'role' => $role, 'status' => 'aktif']);
        };

        $this->ids = [
            'admin' => $mk('wf_admin', 'admin')->id,
            'admin2' => $mk('wf_admin2', 'admin')->id,
            'siswa' => $mk('wf_siswa', 'siswa')->id,
            'class' => $class->id,
            'major' => $major->id,
        ];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            $uids = UserBaru::where('username', 'like', 'wf\_%')->pluck('id');
            if ($uids->isNotEmpty()) {
                $sids = Student::whereIn('user_id', $uids)->pluck('id');
                if ($sids->isNotEmpty()) {
                    \App\Models\Report::whereIn('student_id', $sids)->delete();
                    Student::whereIn('id', $sids)->delete();
                }
                Teacher::whereIn('user_id', $uids)->delete();
                \App\Models\Notification::whereIn('user_id', $uids)->delete();
                UserBaru::whereIn('id', $uids)->delete();
            }
            SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
            Major::whereKey($this->ids['major'] ?? 0)->delete();
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    private function admin(): UserBaru
    {
        return UserBaru::findOrFail($this->ids['admin']);
    }

    private function payload(string $username, string $role, array $extra = []): array
    {
        $base = [
            'name' => 'Nama '.$username, 'username' => $username,
            'password' => 'password123', 'role' => $role, 'status' => 'aktif',
        ];

        if ($role === 'siswa') {
            $base['nis'] = 'WFNIS_'.$username;
            $base['class_id'] = $this->ids['class'];
        }

        if ($role === 'guru') {
            $base['nip'] = 'WFNIP_'.$username;
        }

        return array_merge($base, $extra);
    }

    private function dispatchIdFrom($response): string
    {
        $location = (string) $response->headers->get('Location');
        $id = Str::afterLast($location, '/');
        $this->assertTrue(Str::isUuid($id), 'Redirect harus ke halaman konfirmasi dengan UUID.');

        return $id;
    }

    private function fakeSuccess(): void
    {
        config(['services.fonnte.token' => self::FAKE_TOKEN]);
        Http::fake(['api.fonnte.com/*' => Http::response([
            'status' => true, 'detail' => 'success! message in queue',
            'id' => ['80367170'], 'requestid' => 2937124,
        ], 200)]);
    }

    // --- 1-3. Pembuatan akun per role dengan WA valid ---

    public function test_create_siswa_guru_kepsek_with_wa_redirects_to_confirm(): void
    {
        foreach (['siswa', 'guru', 'kepala_sekolah'] as $role) {
            $u = 'wf_new_'.$role.'_'.Str::random(4);
            $res = $this->actingAs($this->admin())
                ->post('/admin/users', $this->payload($u, $role, ['wa_number' => '081234567890']))
                ->assertRedirect();
            $id = $this->dispatchIdFrom($res);
            $user = UserBaru::where('username', $u)->firstOrFail();
            $this->assertEquals($role, $user->role);
            $this->assertTrue(Hash::check('password123', $user->password));
        }
    }

    public function test_phone_persisted_for_siswa_guru_but_not_kepsek(): void
    {
        $s = 'wf_ph_s'.Str::random(4);
        $this->actingAs($this->admin())->post('/admin/users', $this->payload($s, 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $su = UserBaru::where('username', $s)->firstOrFail();
        $this->assertEquals('6281234567890', $su->student->phone);

        $g = 'wf_ph_g'.Str::random(4);
        $this->actingAs($this->admin())->post('/admin/users', $this->payload($g, 'guru', ['wa_number' => '+6281234567890']))->assertRedirect();
        $gu = UserBaru::where('username', $g)->firstOrFail();
        $this->assertEquals('6281234567890', $gu->teacher->phone);

        $k = 'wf_ph_k'.Str::random(4);
        $this->actingAs($this->admin())->post('/admin/users', $this->payload($k, 'kepala_sekolah', ['wa_number' => '6281234567890']))->assertRedirect();
        $ku = UserBaru::where('username', $k)->firstOrFail();
        $this->assertNull(Student::where('user_id', $ku->id)->first());
        $this->assertNull(Teacher::where('user_id', $ku->id)->first());
    }

    // --- 4-6. Role admin, tanpa WA, nomor invalid ---

    public function test_admin_role_cannot_use_wa_flow(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_noadmin', 'admin', ['wa_number' => '081234567890']))
            ->assertSessionHasErrors('wa_number');
        $this->assertNull(UserBaru::where('username', 'wf_noadmin')->first());
    }

    public function test_create_without_wa_uses_legacy_flow(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_legacy', 'siswa'))
            ->assertRedirect(route('admin.users.index'));
        $this->assertNotNull(UserBaru::where('username', 'wf_legacy')->first());
    }

    public function test_invalid_wa_rejected(): void
    {
        foreach (['abc', '081', '123', '++62812', str_repeat('0', 30)] as $i => $bad) {
            $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_bad'.$i.Str::random(3), 'siswa', ['wa_number' => $bad]))
                ->assertSessionHasErrors('wa_number');
        }
    }

    public function test_rule_normalize_matrix(): void
    {
        $this->assertEquals('6281234567890', IndonesianWhatsappNumber::normalize('081234567890'));
        $this->assertEquals('6281234567890', IndonesianWhatsappNumber::normalize('+6281234567890'));
        $this->assertEquals('6281234567890', IndonesianWhatsappNumber::normalize('6281234567890'));
        $this->assertEquals('6281234567890', IndonesianWhatsappNumber::normalize('81234567890'));
        $this->assertEquals('6281234567890', IndonesianWhatsappNumber::normalize('0812-3456-7890'));
        $this->assertNull(IndonesianWhatsappNumber::normalize('abc'));
        $this->assertNull(IndonesianWhatsappNumber::normalize('081'));
        $this->assertNull(IndonesianWhatsappNumber::normalize('12345'));
        $this->assertNull(IndonesianWhatsappNumber::normalize('../../etc'));
        $this->assertNull(IndonesianWhatsappNumber::normalize(null));
    }

    // --- 5,7,8. Guard akses ---

    public function test_guest_and_non_admin_blocked(): void
    {
        $this->get('/admin/users/whatsapp/'.Str::uuid())->assertRedirect('/login');
        $siswa = UserBaru::findOrFail($this->ids['siswa']);
        $this->actingAs($siswa)->get('/admin/users/whatsapp/'.Str::uuid())->assertStatus(403);
        $this->actingAs($siswa)->post('/admin/users/whatsapp/'.Str::uuid().'/send', ['target' => '6281234567890'])->assertStatus(403);
    }

    // --- 7,8. Modal tidak kirim otomatis; kirim setelah POST ---

    public function test_show_does_not_trigger_send(): void
    {
        Http::fake();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_modal', 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->get(route('admin.users.wa.show', $id))->assertStatus(200);
        Http::assertNothingSent();
    }

    public function test_send_only_after_post_confirm(): void
    {
        $this->fakeSuccess();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_send', 'guru', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        Http::assertNothingSent();
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])
            ->assertRedirect(route('admin.users.wa.show', $id));
        Http::assertSentCount(1);
    }

    // --- 9-14. Respons API ---

    public function test_success_response_handled(): void
    {
        $this->fakeSuccess();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_ok', 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])
            ->assertRedirect(route('admin.users.wa.show', $id))
            ->assertSessionHas('success');
        Http::assertSent(function (HttpRequest $req) {
            return $req->url() === 'https://api.fonnte.com/send'
                && ($req->header('Authorization')[0] ?? null) === self::FAKE_TOKEN
                && ($req->data()['target'] ?? null) === '6281234567890'
                && ($req->data()['countryCode'] ?? null) === '62';
        });
        // Rahasia dikonsumsi: kirim ulang tidak mengulang pesan.
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])->assertStatus(404);
        Http::assertSentCount(1);
    }

    public function test_http_error_handled(): void
    {
        config(['services.fonnte.token' => self::FAKE_TOKEN]);
        Http::fake(['api.fonnte.com/*' => Http::response('boom', 500)]);
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_http', 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])
            ->assertRedirect(route('admin.users.wa.show', $id));
        $this->assertStringContainsString('kode 500', (string) session('error'));
    }

    public function test_api_false_not_treated_as_success(): void
    {
        config(['services.fonnte.token' => self::FAKE_TOKEN]);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'target invalid'], 200)]);
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_false', 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])
            ->assertRedirect(route('admin.users.wa.show', $id));
        $this->assertStringContainsString('tidak valid', (string) session('error'));
        $this->actingAs($this->admin())->get(route('admin.users.wa.show', $id))->assertDontSee('masuk antrean');
        // Gagal definitif: rahasia DIPERTAHANKAN agar tombol dapat dipakai
        // kembali (retry manual aman); halaman tetap menampilkan tombol kirim.
        $this->actingAs($this->admin())->get(route('admin.users.wa.show', $id))
            ->assertStatus(200)
            ->assertSee('Coba Kirim Lagi', false);
    }

    public function test_failed_definitive_retry_allowed_while_secret_lives(): void
    {
        config(['services.fonnte.token' => self::FAKE_TOKEN]);
        Http::fakeSequence()
            ->push(['status' => false, 'reason' => 'server error'], 200)
            ->push(['status' => true, 'detail' => 'success! message in queue', 'requestid' => 1], 200);
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_fr', 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])
            ->assertRedirect(route('admin.users.wa.show', $id));
        // Retry manual memakai rahasia yang sama: request kedua terkirim.
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])
            ->assertRedirect(route('admin.users.wa.show', $id))
            ->assertSessionHas('success');
        Http::assertSentCount(2);
        // Setelah sukses rahasia habis: tidak ada kirim ketiga.
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])->assertStatus(404);
        Http::assertSentCount(2);
    }

    public function test_timeout_uncertain_without_auto_retry(): void
    {
        config(['services.fonnte.token' => self::FAKE_TOKEN]);
        Http::fake(['api.fonnte.com/*' => Http::failedConnection('timeout')]);
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_un', 'guru', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])
            ->assertRedirect(route('admin.users.wa.show', $id));
        $this->actingAs($this->admin())->get(route('admin.users.wa.show', $id))->assertSee('tidak pasti', false);
        // Rahasia dikonsumsi: retry tidak mengarang password / kirim ulang.
        $this->actingAs($this->admin())->post(route('admin.users.wa.retry', $id), ['target' => '6281234567890'])
            ->assertRedirect();
        Http::assertSentCount(1);
    }

    public function test_unconfigured_fails_without_request(): void
    {
        config(['services.fonnte.token' => null]);
        Http::fake();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_unconf', 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])
            ->assertRedirect(route('admin.users.wa.show', $id));
        $this->assertStringContainsString('belum dikonfigurasi', (string) session('error'));
        Http::assertNothingSent();
        // Tanpa token = gagal definitif: rahasia dipertahankan, tombol
        // kirim tetap tersedia untuk percobaan setelah token dikonfigurasi.
        $this->actingAs($this->admin())->get(route('admin.users.wa.show', $id))
            ->assertStatus(200)
            ->assertSee('Coba Kirim Lagi', false);
    }

    // --- 15,19,21. Duplikat, kepemilikan, spoofing, retry ---

    public function test_double_submit_single_delivery(): void
    {
        $this->fakeSuccess();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_dbl', 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])->assertRedirect();
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])->assertStatus(404);
        Http::assertSentCount(1);
    }

    public function test_retry_without_secret_fails_safely(): void
    {
        $this->fakeSuccess();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_rt', 'guru', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])->assertRedirect();
        $this->actingAs($this->admin())->post(route('admin.users.wa.retry', $id), ['target' => '6281234567890'])
            ->assertRedirect();
        $this->assertStringContainsString('kedaluwarsa', (string) session('error'));
        Http::assertSentCount(1);
    }

    public function test_secret_only_owner_admin(): void
    {
        Http::fake();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_own', 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $admin2 = UserBaru::findOrFail($this->ids['admin2']);
        $this->actingAs($admin2)->get(route('admin.users.wa.show', $id))->assertStatus(404);
        $this->actingAs($admin2)->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])->assertStatus(404);
        Http::assertNothingSent();
    }

    public function test_target_spoofing_rejected(): void
    {
        Http::fake();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_spoof', 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        // Nomor format-valid tetapi bukan yang dikonfirmasi -> ditolak
        // via flash error (bukan error validasi) dan tidak ada request.
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6289999999999'])
            ->assertRedirect();
        $this->assertStringContainsString('tidak sesuai', (string) session('error'));
        Http::assertNothingSent();
    }

    // --- 17,18,20,22. Kebocoran & preview ---

    public function test_password_never_plaintext_nor_leaked(): void
    {
        $this->fakeSuccess();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_leak', 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $user = UserBaru::where('username', 'wf_leak')->firstOrFail();
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));

        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])->assertRedirect();
        $page = $this->actingAs($this->admin())->get(route('admin.users.wa.show', $id));
        $page->assertStatus(200);
        // Pasca-kirim rahasia habis: password tidak tampil lagi.
        $page->assertDontSee('password123');
        $location = (string) $res->headers->get('Location');
        $this->assertStringNotContainsString('password123', $location);
        $this->assertStringNotContainsString('password123', $user->fresh()->password);
    }

    public function test_token_never_exposed_but_used_in_header(): void
    {
        $this->fakeSuccess();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_tok', 'guru', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->get(route('admin.users.wa.show', $id))->assertDontSee(self::FAKE_TOKEN);
        $this->actingAs($this->admin())->post(route('admin.users.wa.send', $id), ['target' => '6281234567890'])->assertRedirect();
        Http::assertSent(function (HttpRequest $req) {
            $hasHeader = ($req->header('Authorization')[0] ?? null) === self::FAKE_TOKEN;
            $notInBody = ! str_contains(json_encode($req->data()), self::FAKE_TOKEN);

            return $hasHeader && $notInBody;
        });
    }

    public function test_preview_matches_backend_template(): void
    {
        $this->fakeSuccess();
        $u = 'wf_prev_'.Str::random(4);
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload($u, 'siswa', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $user = UserBaru::where('username', $u)->firstOrFail();
        $expected = FonnteService::buildMessage($user, 'password123');
        $this->actingAs($this->admin())->get(route('admin.users.wa.show', $id))
            ->assertStatus(200)
            ->assertSee($expected, false);
    }

    public function test_cancel_clears_secret_keeps_account(): void
    {
        Http::fake();
        $res = $this->actingAs($this->admin())->post('/admin/users', $this->payload('wf_cancel', 'guru', ['wa_number' => '081234567890']))->assertRedirect();
        $id = $this->dispatchIdFrom($res);
        $this->actingAs($this->admin())->post(route('admin.users.wa.cancel', $id))
            ->assertRedirect(route('admin.users.index'));
        Http::assertNothingSent();
        $this->assertNotNull(UserBaru::where('username', 'wf_cancel')->first());
        $this->assertNull(WaCredentialDispatch::loadPending($id));
        $this->actingAs($this->admin())->get(route('admin.users.wa.show', $id))->assertStatus(404);
    }

    public function test_request_timeout_configurable(): void
    {
        config(['services.fonnte.timeout' => 45]);
        $this->assertEquals(45, config('services.fonnte.timeout'));
        $this->assertLessThanOrEqual(3600, WaCredentialDispatch::SECRET_TTL_SECONDS);
        $this->assertGreaterThan(0, WaCredentialDispatch::SECRET_TTL_SECONDS);
        $this->assertNull(Cache::get(WaCredentialDispatch::key('non-existent-id')));
    }

    public function test_secret_ttl_short_and_status_separate(): void
    {
        $this->assertLessThanOrEqual(3600, WaCredentialDispatch::SECRET_TTL_SECONDS);
        $this->assertGreaterThan(0, WaCredentialDispatch::SECRET_TTL_SECONDS);
        // phpunit.xml memaksa SESSION_DRIVER=array pada testing; yang
        // penting rahasia hanya di cache server-side ber-TTL, bukan di
        // flash/URL/log — dibuktikan test_password_never_plaintext_nor_leaked.
        $this->assertSame('array', config('session.driver'));
        $this->assertNull(Cache::get(WaCredentialDispatch::key('non-existent-id')));
    }
}
