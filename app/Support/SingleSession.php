<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Penanda sesi aktif per user untuk konsep FIRST LOGIN WINS.
 *
 * Penyimpanan memakai cache store default (bukan tabel baru, bukan kolom
 * baru di users_baru) dengan key per user ID. Login pertama yang berhasil
 * menyimpan marker; percobaan login berikutnya DITOLAK selama marker masih
 * merujuk ke sesi yang valid (tidak di-overwrite). Middleware
 * ValidateAuthenticatedUser tetap menjadi pengaman untuk sesi yang tidak
 * cocok. TTL mengikuti session lifetime agar marker tidak mengunci akun
 * selamanya; logout normal menghapus marker sehingga akun bisa dipakai
 * login kembali.
 */
class SingleSession
{
    public static function key(int|string $userId): string
    {
        return 'single_session:user:'.$userId;
    }

    public static function ttlMinutes(): int
    {
        return (int) config('session.lifetime', 120);
    }

    public static function remember(int|string $userId, string $sessionId): void
    {
        Cache::put(self::key($userId), $sessionId, now()->addMinutes(self::ttlMinutes()));
    }

    /**
     * Klaim marker secara atomik untuk login baru (first login wins).
     * Berhasil bila belum ada marker ATAU marker lama sudah tidak aktif
     * (expired/logout/GC) — dalam kasus basi, marker lama diganti dengan
     * sesi baru. Gagal bila marker masih merujuk sesi yang valid, sehingga
     * login kedua tidak boleh overwrite marker Device A. Mengembalikan
     * session ID aktif bila klaim gagal (untuk kebutuhan pesan/debug).
     *
     * @return array{claimed: bool, active: ?string}
     */
    public static function tryClaim(int|string $userId, string $sessionId): array
    {
        $store = Cache::getStore();

        if (! method_exists($store, 'add')) {
            if (self::isActive($userId)) {
                return ['claimed' => false, 'active' => self::activeSessionId($userId)];
            }

            self::remember($userId, $sessionId);

            return ['claimed' => true, 'active' => null];
        }

        $key = self::key($userId);
        $ttlSeconds = max(60, self::ttlMinutes() * 60);

        if ($store->add($key, $sessionId, $ttlSeconds)) {
            return ['claimed' => true, 'active' => null];
        }

        if (self::isActive($userId)) {
            return ['claimed' => false, 'active' => self::activeSessionId($userId)];
        }

        // Marker basi: ganti dengan sesi baru agar akun tidak terkunci
        // selamanya (expired/logout tanpa hapus marker/GC/browser tutup).
        self::remember($userId, $sessionId);

        return ['claimed' => true, 'active' => null];
    }

    /**
     * Segarkan TTL marker milik sesi ini (sliding window mengikuti session
     * lifetime) agar Device A yang terus aktif tidak tiba-tiba dianggap
     * basi. Tidak menyentuh marker milik sesi lain.
     */
    public static function touch(int|string $userId, string $sessionId): void
    {
        if (self::isCurrent($userId, $sessionId)) {
            self::remember($userId, $sessionId);
        }
    }

    public static function has(int|string $userId): bool
    {
        return Cache::has(self::key($userId));
    }

    public static function isCurrent(int|string $userId, ?string $sessionId): bool
    {
        if (! $sessionId) {
            return false;
        }

        return Cache::get(self::key($userId)) === $sessionId;
    }

    public static function activeSessionId(int|string $userId): ?string
    {
        $value = Cache::get(self::key($userId));

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Marker dianggap aktif bila masih ada di cache DAN sesi yang dirujuk
     * masih valid. Validitas sesi dicek dari tabel sessions (driver
     * database) memakai last_activity + session lifetime: sesi kedaluwarsa
     * atau sudah hilang (logout/GC/browser ditutup) berarti tidak aktif.
     * Untuk driver non-database, keberadaan marker di cache menjadi acuan
     * (tanpa heartbeat baru).
     */
    public static function isActive(int|string $userId): bool
    {
        $sessionId = self::activeSessionId($userId);

        if ($sessionId === null) {
            return false;
        }

        if ((string) config('session.driver', 'database') !== 'database') {
            return true;
        }

        try {
            $row = DB::table((string) config('session.table', 'sessions'))
                ->where('id', $sessionId)
                ->first(['id', 'last_activity']);
        } catch (\Throwable $e) {
            return true;
        }

        if ($row === null) {
            return false;
        }

        $lifetime = max(1, self::ttlMinutes());
        $lastActivity = (int) ($row->last_activity ?? 0);

        return $lastActivity >= now()->subMinutes($lifetime)->getTimestamp();
    }

    /**
     * Hapus marker sesi aktif (dipakai saat logout normal). Idempoten:
     * hanya menghapus bila marker milik sesi yang logout atau bila sesi
     * yang dirujuk sudah tidak valid.
     */
    public static function forget(int|string $userId, ?string $sessionId = null): void
    {
        if ($sessionId !== null && $sessionId !== '') {
            $current = self::activeSessionId($userId);

            if ($current !== null && $current !== $sessionId && self::isActive($userId)) {
                return;
            }
        }

        Cache::forget(self::key($userId));
    }
}
