<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\UserBaru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UI upload foto laporan siswa: Ambil Foto memakai kamera real-time
 * (getUserMedia + <video> live + <canvas> capture -> File) dengan kamera
 * belakang sebagai default, + Pilih dari Galeri (fallback). Satu field
 * submit photos[] masuk pipeline backend yang sama, preview + hapus +
 * counter. Backend tidak diubah; test ini validasi struktur Blade/JS.
 */
class SiswaPhotoUploadUiTest extends TestCase
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

        $siswa = UserBaru::where('username', 'fpu_siswa')->first()
            ?: UserBaru::create(['name' => 'Siswa FPU', 'username' => 'fpu_siswa', 'password' => Hash::make('password123'), 'role' => 'siswa', 'status' => 'aktif']);

        $major = Major::firstOrCreate(['name' => 'TEST_MAJOR_FPU'], ['status' => 'aktif']);
        $class = SchoolClass::where('name', 'TEST_KELAS_FPU')->first()
            ?: SchoolClass::create(['major_id' => $major->id, 'name' => 'TEST_KELAS_FPU', 'status' => 'aktif']);
        if (! Student::where('user_id', $siswa->id)->exists()) {
            Student::create(['user_id' => $siswa->id, 'nis' => 'FPUNIS001', 'class_id' => $class->id]);
        }

        $this->ids = ['siswa' => $siswa->id, 'major' => $major->id, 'class' => $class->id];
    }

    protected function tearDown(): void
    {
        try {
            $this->reconnect();
            if (! empty($this->ids)) {
                Student::where('nis', 'FPUNIS001')->delete();
                UserBaru::whereIn('username', ['fpu_siswa'])->delete();
                SchoolClass::whereKey($this->ids['class'] ?? 0)->delete();
                Major::whereKey($this->ids['major'] ?? 0)->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    public function test_create_page_has_camera_and_gallery_inputs(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswa']);
        $html = $this->actingAs($siswa)->get('/siswa/laporan/create')
            ->assertStatus(200)->getContent();

        // Kamera real-time: getUserMedia + <video> + <canvas>, kamera
        // belakang sebagai default, tanpa input file capture sebagai utama.
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*capture="environment"[^>]*>/',
            $html,
            'capture=environment tidak boleh lagi jadi mekanisme utama kamera.'
        );
        $this->assertStringContainsString('navigator.mediaDevices.getUserMedia', $html, 'Kamera harus memakai getUserMedia.');
        $this->assertStringContainsString('data-sl-camera-video', $html, 'Harus ada elemen <video> untuk live preview.');
        $this->assertStringContainsString('data-sl-camera-canvas', $html, 'Harus ada <canvas> untuk capture frame.');
        $this->assertStringContainsString('data-sl-camera-capture', $html, 'Harus ada tombol capture di panel kamera.');
        $this->assertStringContainsString('data-sl-camera-close', $html, 'Harus ada tombol Tutup Kamera.');
        $this->assertStringContainsString("facingMode", $html, 'Harus meminta kamera belakang (facingMode environment).');
        $this->assertStringContainsString("ideal: 'environment'", $html, 'facingMode ideal environment.');
        $this->assertStringContainsString('audio: false', $html, 'Tidak boleh meminta microphone.');
        // Stream lifecycle: satu stream, stop semua track saat tutup/pindah.
        $this->assertStringContainsString('cameraStream', $html, 'Harus ada state stream tunggal.');
        $this->assertStringContainsString('getTracks().forEach', $html, 'Tutup kamera harus stop semua track.');
        $this->assertStringContainsString('pagehide', $html, 'Stream harus dilepas saat pindah halaman.');
        // Permission & fallback: pesan jelas + galeri tetap ada.
        $this->assertStringContainsString('NotAllowedError', $html, 'Harus menangani permission kamera ditolak.');
        $this->assertStringContainsString('Pilih dari Galeri', $html);
        $this->assertStringContainsString('drawImage', $html, 'Capture harus video -> canvas (drawImage).');
        $this->assertStringContainsString('toBlob', $html, 'Canvas harus dikonversi ke Blob.');
        $this->assertStringContainsString("new File([blob]", $html, 'Blob kamera harus menjadi File untuk pipeline upload.');
        $this->assertStringContainsString("image/jpeg", $html, 'Output kamera image/jpeg (lolos validasi backend).');
        // Kamera tidak auto-start: getUserMedia hanya dipanggil dari klik tombol.
        $this->assertDoesNotMatchRegularExpression('/getUserMedia\(\)/', $html, 'Tidak boleh memanggil getUserMedia tanpa argumen/constraint.');
        // Galeri: fallback multiple tanpa capture.
        preg_match('/<input[^>]*data-sl-photos-gallery[^>]*>/', $html, $gal);
        $this->assertNotEmpty($gal, 'Input galeri harus ada.');
        $this->assertStringContainsString('multiple', $gal[0], 'Input galeri harus multiple sebagai fallback.');
        $this->assertStringNotContainsString('capture=', $gal[0], 'Input galeri tidak boleh memaksa kamera.');
        // Satu field submit ke backend yang sama (pipeline tidak berubah).
        preg_match_all('/name="photos\[\]"/', $html, $m);
        $this->assertCount(1, $m[0], 'Hanya satu field name=photos[] yang disubmit ke backend.');
        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="photos\[\]"[^>]*required[^>]*>/',
            $html,
            'Field photos[] tetap required (backend: min 1 foto).'
        );
        // Responsive root-cause: form/kamera/grid/tombol terkunci ke parent,
        // tanpa overflow-x hidden dan tanpa mengubah tampilan desktop.
        $css = @file_get_contents(public_path('css/si-lpiket.css'));
        $this->assertNotFalse($css, 'CSS si-lpiket harus terbaca.');
        foreach (['.sl-camera {', '.sl-camera video {', '.sl-photo-actions {', '.sl-form-actions {', '.photo-grid {'] as $sel) {
            $this->assertStringContainsString($sel, $css, 'Selector '.$sel.' harus ada.');
        }
        $this->assertStringContainsString('max-width: 100%', $css);
        $this->assertDoesNotMatchRegularExpression('/html\s*,\s*body\s*\{\s*overflow-x\s*:/', $css, 'Tidak boleh memakai solusi palsu overflow-x hidden di html/body.');
        $this->assertDoesNotMatchRegularExpression('/body\s*\{\s*[^}]*overflow-x\s*:\s*hidden/', $css, 'Tidak boleh memakai solusi palsu overflow-x hidden di body.');
        $this->assertStringContainsString('sl-form-actions', $html, 'Tombol Kirim/Batal harus dalam wrapper flex.');
        // Tombol pemicu + preview + counter + pesan fallback kamera.
        $this->assertStringContainsString('data-sl-photo-camera-btn', $html);
        $this->assertStringContainsString('Ambil Foto', $html);
        $this->assertStringContainsString('data-sl-photo-gallery-btn', $html);
        $this->assertStringContainsString('Pilih dari Galeri', $html);
        $this->assertStringContainsString('id="photos-preview"', $html);
        $this->assertStringContainsString('id="photos-count"', $html);
        $this->assertStringContainsString('0 / 5 foto', $html);
        $this->assertStringContainsString('Maksimal 5 foto', $html);
        $this->assertStringContainsString('8 MB', $html);
        // Batas JS selaras backend: maks 5, 8 MB, jpg/jpeg/png/webp.
        $this->assertStringContainsString('MAX_FILES = 5', $html);
        $this->assertStringContainsString('8 * 1024 * 1024', $html);
        $this->assertStringContainsString('image/webp', $html);
        // Hapus per foto + validasi submit sisi klien.
        $this->assertStringContainsString('data-sl-photo-remove', $html);
        $this->assertStringContainsString('minimal 1 foto', $html);
        // Tanpa localStorage/session/base64 permanen; preview FileReader tetap sementara di memori.
        $this->assertDoesNotMatchRegularExpression('/localStorage|sessionStorage/i', $html);
    }

    public function test_create_page_layout_scoped_and_order_intact(): void
    {
        $siswa = UserBaru::findOrFail($this->ids['siswa']);
        $html = $this->actingAs($siswa)->get('/siswa/laporan/create')
            ->assertStatus(200)->getContent();

        // Scope isolasi: hanya halaman ini memakai .sl-report-create.
        $this->assertStringContainsString('sl-card sl-report-create', $html);
        $this->assertStringContainsString('sl-report-form', $html);
        $this->assertStringContainsString('action="'.route('siswa.reports.store').'"', $html, 'Form tetap ke route yang sama.');

        // Urutan field tetap: judul, kategori, lokasi, deskripsi, foto, aksi.
        $posTitle = strpos($html, 'for="title"');
        $posCat = strpos($html, 'for="category_id"');
        $posLoc = strpos($html, 'for="location"');
        $posDesc = strpos($html, 'for="description"');
        $posPhoto = strpos($html, 'data-sl-photo-upload');
        $posSubmit = strpos($html, 'Kirim Laporan');
        $this->assertNotFalse($posTitle);
        $this->assertTrue($posTitle < $posCat && $posCat < $posLoc && $posLoc < $posDesc && $posDesc < $posPhoto && $posPhoto < $posSubmit, 'Urutan form harus tetap.');

        // Halaman lain tidak memakai scope ini (regresi).
        $dash = $this->actingAs($siswa)->get('/siswa')->assertStatus(200)->getContent();
        $this->assertStringNotContainsString('sl-report-create', $dash, 'Dashboard siswa tidak boleh terpengaruh.');
        $index = $this->actingAs($siswa)->get('/siswa/laporan')->assertStatus(200)->getContent();
        $this->assertStringNotContainsString('sl-report-create', $index, 'Laporan Saya tidak boleh terpengaruh.');

        // CSS scope: aturan terisolasi ada, tanpa width 100vw baru di scope.
        $css = @file_get_contents(public_path('css/si-lpiket.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.sl-report-create {', $css);
        $this->assertStringContainsString('max-width: 720px', $css, 'Desktop dibatasi lebar baca.');
        $this->assertDoesNotMatchRegularExpression('/\.sl-report-create[^{]*\{[^}]*100vw/', $css, 'Scope halaman dilarang memakai 100vw.');
    }
}
