<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Penanda sesi aktif terbaru per user untuk konsep ONE ACCOUNT = ONE ACTIVE SESSION.
 *
 * Penyimpanan memakai cache store default (bukan tabel baru, bukan kolom
 * baru di users_baru) dengan key per user ID. Setiap login sukses menimpa
 * marker dengan session ID terbaru; middleware ValidateAuthenticatedUser
 * menolak sesi yang session ID-nya tidak cocok. TTL mengikuti session
 * lifetime agar marker tidak bocor selamanya.
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
}
