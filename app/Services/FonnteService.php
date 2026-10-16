<?php

namespace App\Services;

use App\Models\UserBaru;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Layanan pengiriman WhatsApp via Fonnte (dasar: docs.fonnte.com/api-send-message).
 *
 * Kontrak terverifikasi:
 * - POST https://api.fonnte.com/send (form-encoded), param target/message/countryCode.
 * - Header `Authorization: TOKEN` langsung (tanpa skema Bearer).
 * - Sukses: {"status": true, "detail": "...", ...}; gagal: {"status": false, "reason": "..."}.
 * - Sukses berarti pesan MASUK ANTREAN Fonnte, bukan terkirim/dibaca.
 *
 * Keamanan: token hanya dibaca dari config; tidak pernah ditulis ke log,
 * respons, atau pesan error yang mencapai pengguna.
 */
class FonnteService
{
    public const ALLOWED_ROLES = ['siswa', 'guru', 'kepala_sekolah'];

    public const DEFAULT_URL = 'https://api.fonnte.com/send';

    public const CONNECT_TIMEOUT_SECONDS = 10;

    public const REQUEST_TIMEOUT_SECONDS = 30;

    public const COUNTRY_CODE = '62';

    public static function configured(): bool
    {
        return (string) config('services.fonnte.token', '') !== '';
    }

    public static function endpoint(): string
    {
        $url = (string) config('services.fonnte.url', self::DEFAULT_URL);

        return $url !== '' ? $url : self::DEFAULT_URL;
    }

    /**
     * @return array{label: string}
     */
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'siswa' => 'Siswa',
            'guru' => 'Guru',
            'kepala_sekolah' => 'Kepala Sekolah',
            default => $role,
        };
    }

    /**
     * Template pesan kredensial. Satu-satunya pembentuk pesan agar preview
     * pada modal sama persis dengan yang dikirim backend.
     */
    public static function buildMessage(UserBaru $user, string $plainPassword): string
    {
        $lines = [
            'Halo '.$user->name.', akun SI-LPiket Anda telah dibuat.',
            '',
            'Nama: '.$user->name,
            'Role: '.self::roleLabel((string) $user->role),
            'Username: '.$user->username,
            'Password: '.$plainPassword,
            '',
            'Silakan login ke aplikasi SI-LPiket dan segera ubah password Anda.',
            'Jaga kerahasiaan kredensial ini dan jangan bagikan kepada siapa pun.',
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array{outcome: 'sent'|'failed'|'uncertain', reason: ?string, reference: ?string}
     */
    public function sendCredential(string $target, UserBaru $user, string $plainPassword): array
    {
        if (! static::configured()) {
            return ['outcome' => 'failed', 'reason' => 'unconfigured', 'reference' => null];
        }

        $payload = [
            'target' => $target,
            'message' => static::buildMessage($user, $plainPassword),
            'countryCode' => self::COUNTRY_CODE,
        ];

        try {
            $timeout = max(5, (int) config('services.fonnte.timeout', self::REQUEST_TIMEOUT_SECONDS));
            $response = Http::withHeaders(['Authorization' => (string) config('services.fonnte.token')])
                ->connectTimeout(min(self::CONNECT_TIMEOUT_SECONDS, $timeout))
                ->timeout($timeout)
                ->asForm()
                ->post(static::endpoint(), $payload);
        } catch (ConnectionException $e) {
            // Timeout/putus: pesan mungkin sudah diterima Fonnte.
            report($e);

            return ['outcome' => 'uncertain', 'reason' => 'timeout', 'reference' => null];
        } catch (Throwable $e) {
            report($e);

            return ['outcome' => 'failed', 'reason' => 'network', 'reference' => null];
        }

        if (! $response->successful()) {
            return ['outcome' => 'failed', 'reason' => 'http_'.$response->status(), 'reference' => null];
        }

        $body = $response->json();

        if (! is_array($body)) {
            return ['outcome' => 'failed', 'reason' => 'invalid_response', 'reference' => null];
        }

        $status = $body['status'] ?? $body['Status'] ?? null;

        if ($status === true || $status === 1 || $status === 'true') {
            $reference = $body['requestid'] ?? $body['id'][0] ?? null;

            return [
                'outcome' => 'sent',
                'reason' => null,
                'reference' => is_scalar($reference) ? (string) $reference : null,
            ];
        }

        $reason = $body['reason'] ?? null;

        return [
            'outcome' => 'failed',
            'reason' => is_string($reason) && $reason !== '' ? $reason : 'rejected',
            'reference' => null,
        ];
    }

    /**
     * Pesan aman untuk admin (tanpa token, tanpa isi pesan, tanpa password).
     */
    public static function friendlyMessage(string $reason): string
    {
        return match (true) {
            $reason === 'unconfigured' => 'Integrasi WhatsApp belum dikonfigurasi. Hubungi pengelola sistem untuk mengatur token Fonnte.',
            $reason === 'timeout' => 'Hasil pengiriman tidak pasti (koneksi terputus). Pesan mungkin sudah terkirim — periksa ke penerima sebelum mengirim ulang.',
            $reason === 'network' => 'Pengiriman gagal karena gangguan jaringan. Silakan coba lagi.',
            $reason === 'target invalid' || $reason === 'target_invalid' => 'Nomor tujuan tidak valid menurut Fonnte. Periksa kembali nomor WhatsApp.',
            $reason === 'insufficient quota' => 'Kuota pengiriman Fonnte habis. Pengiriman ditolak dan tidak disimpan.',
            str_starts_with($reason, 'http_') => 'Layanan WhatsApp menolak permintaan (kode '.substr($reason, 5).'). Silakan coba lagi nanti.',
            default => 'Pengiriman WhatsApp gagal. Silakan coba lagi.',
        };
    }
}
