<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserBaru;
use App\Rules\IndonesianWhatsappNumber;
use App\Services\FonnteService;
use App\Support\WaCredentialDispatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Pengiriman kredensial akun via WhatsApp (Fonnte) dengan konfirmasi admin.
 *
 * Guard berlapis: middleware auth+role:admin (route), CSRF grup web,
 * validasi role target (siswa/guru/kepala_sekolah), kepemilikan alur
 * (admin pembuat + user target), nomor terikat saat store (tidak dapat
 * diganti via request kirim), lock anti-ganda, dan tidak ada pengiriman
 * otomatis — hanya setelah POST konfirmasi eksplisit.
 */
class AccountWhatsappController extends Controller
{
    public function __construct(private readonly FonnteService $fonnte) {}

    /**
     * Halaman status pengiriman untuk satu alur (dipakai modal).
     */
    public function show(Request $request, string $id)
    {
        $pending = WaCredentialDispatch::loadPending($id);
        $status = WaCredentialDispatch::loadStatus($id);

        $record = $pending ?? $status;

        if (! is_array($record)
            || ! WaCredentialDispatch::ownedBy($record, WaCredentialDispatch::currentAdminId())) {
            abort(404);
        }

        $user = UserBaru::findOrFail((int) $record['user_id']);

        if (! in_array($user->role, FonnteService::ALLOWED_ROLES, true)) {
            abort(404);
        }

        // Password asli + preview hanya selama rahasia masih ada (alur
        // konfirmasi berjalan). Setelah terkirim/dibatalkan/kedaluwarsa,
        // halaman hanya menampilkan status tanpa kredensial.
        $plainPassword = $pending !== null ? WaCredentialDispatch::decryptPassword($pending) : null;

        return view('admin.users.wa-result', [
            'dispatchId' => $id,
            'user' => $user,
            'target' => (string) $record['target'],
            'status' => $status,
            'hasSecret' => $pending !== null && $plainPassword !== null,
            'preview' => $plainPassword !== null
                ? FonnteService::buildMessage($user, $plainPassword)
                : null,
            'plainPassword' => $plainPassword,
        ]);
    }

    /**
     * Kirim kredensial (POST konfirmasi eksplisit dari modal).
     */
    public function send(Request $request, string $id): RedirectResponse
    {
        $pending = WaCredentialDispatch::loadPending($id);

        if (! is_array($pending)
            || ! WaCredentialDispatch::ownedBy($pending, WaCredentialDispatch::currentAdminId())) {
            abort(404);
        }

        $adminId = WaCredentialDispatch::currentAdminId();
        $user = UserBaru::findOrFail((int) $pending['user_id']);

        if (! in_array($user->role, FonnteService::ALLOWED_ROLES, true)) {
            WaCredentialDispatch::consume($id);

            return back()->with('error', 'Akun ini tidak memenuhi syarat pengiriman WhatsApp.');
        }

        // Nomor terikat saat store; request kirim tidak boleh menggantinya.
        $request->validate([
            'target' => ['required', 'string', new IndonesianWhatsappNumber()],
        ]);

        $want = IndonesianWhatsappNumber::normalize($request->input('target'));

        if ($want === null || ! hash_equals((string) $pending['target'], $want)) {
            return back()->with('error', 'Nomor tujuan tidak sesuai dengan data konfirmasi.');
        }

        $target = (string) $pending['target'];

        // Cegah dua request bersamaan untuk percobaan yang sama.
        $lock = Cache::lock(WaCredentialDispatch::lockKey($id), WaCredentialDispatch::LOCK_SECONDS);

        try {
            $lock->block(5);
        } catch (\Throwable $e) {
            return back()->with('error', 'Pengiriman sedang diproses. Tunggu sebentar lalu muat ulang.');
        }

        try {
            $plain = WaCredentialDispatch::decryptPassword($pending);

            if ($plain === null) {
                return back()->with('error', 'Data kredensial sudah kedaluwarsa. Buat ulang kata sandi melalui form edit bila perlu mengirim ulang.');
            }

            $result = $this->fonnte->sendCredential($target, $user, $plain);

            if ($result['outcome'] === 'uncertain') {
                // Hasil tak pasti: tampilkan status + hapus rahasia agar
                // retry tidak mengarang password; admin memeriksa dulu.
                WaCredentialDispatch::consume($id);
                WaCredentialDispatch::storeStatus($id, $adminId, $user->id, $target, [
                    'outcome' => 'uncertain',
                    'reason' => $result['reason'],
                    'reference' => $result['reference'],
                    'at' => now()->toIso8601String(),
                ]);

                return redirect()->route('admin.users.wa.show', $id)
                    ->with('error', FonnteService::friendlyMessage((string) ($result['reason'] ?? 'timeout')));
            }

            WaCredentialDispatch::storeStatus($id, $adminId, $user->id, $target, [
                'outcome' => $result['outcome'],
                'reason' => $result['reason'],
                'reference' => $result['reference'],
                'at' => now()->toIso8601String(),
            ]);

            if ($result['outcome'] === 'sent') {
                // Terkirim: rahasia dikonsumsi agar tidak ada kirim ulang
                // password yang sama; halaman hanya menampilkan status.
                WaCredentialDispatch::consume($id);

                return redirect()->route('admin.users.wa.show', $id)
                    ->with('success', 'Pesan WhatsApp masuk antrean pengiriman Fonnte.');
            }

            // Gagal definitif: rahasia DIPERTAHANKAN dalam TTL agar tombol
            // dapat dipakai kembali untuk retry manual yang aman. Halaman
            // konfirmasi tetap menampilkan password + preview + tombol aktif.
            return redirect()->route('admin.users.wa.show', $id)
                ->with('error', FonnteService::friendlyMessage((string) ($result['reason'] ?? 'rejected')));
        } finally {
            try {
                $lock->release();
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * Retry manual: hanya bila rahasia masih ada (belum kedaluwarsa/
     * terpakai). Setelah rahasia hilang, retry tidak mengarang password.
     */
    public function retry(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $pending = WaCredentialDispatch::loadPending($id);

        if (! is_array($pending)
            || ! WaCredentialDispatch::ownedBy($pending, WaCredentialDispatch::currentAdminId())) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Data pengiriman tidak ditemukan.'], 404)
                : redirect()->route('admin.users.wa.show', $id)
                    ->with('error', 'Data pengiriman sudah kedaluwarsa. Periksa status sebelum memutuskan pengiriman ulang.');
        }

        return $this->send($request, $id);
    }

    /**
     * Batalkan alur: hapus rahasia segera. Akun yang sudah tersimpan
     * tidak diubah/dihapus otomatis.
     */
    public function cancel(Request $request, string $id): RedirectResponse
    {
        $pending = WaCredentialDispatch::loadPending($id);

        if (is_array($pending)
            && WaCredentialDispatch::ownedBy($pending, WaCredentialDispatch::currentAdminId())) {
            WaCredentialDispatch::consume($id);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Pengiriman WhatsApp dibatalkan. Akun tetap tersimpan.');
    }
}
