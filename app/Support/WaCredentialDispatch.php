<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Pengiriman kredensial WA: status sementara + rahasia sekali-pakai.
 *
 * - Password asli dienkripsi APP_KEY (Crypt) sebelum masuk cache; plaintext
 *   tidak pernah ke DB, log, URL, queue, atau flash session.
 * - Kunci cache acak (UUID) + terikat admin pembuat + user target.
 * - Rahasia dihapus segera setelah percobaan kirim / batal / kedaluwarsa.
 * - Status hasil (tanpa password) disimpan terpisah agar kegagalan tetap
 *   terlihat tanpa menyimpan kredensial.
 */
final class WaCredentialDispatch
{
    public const SECRET_TTL_SECONDS = 900;

    public const STATUS_TTL_SECONDS = 3600;

    public const LOCK_SECONDS = 30;

    public static function key(string $id): string
    {
        return 'wa_dispatch:'.$id;
    }

    public static function statusKey(string $id): string
    {
        return 'wa_dispatch_status:'.$id;
    }

    public static function lockKey(string $id): string
    {
        return 'wa_dispatch:'.$id.':lock';
    }

    /**
     * @return array{id: string}|null null bila enkripsi gagal / APP_KEY belum siap.
     */
    public static function createPending(int $adminId, int $userId, string $target): ?array
    {
        $password = request()->input('password');

        if (! is_string($password) || $password === '') {
            return null;
        }

        try {
            $cipher = Crypt::encryptString($password);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $id = (string) Str::uuid();

        Cache::put(self::key($id), [
            'admin_id' => $adminId,
            'user_id' => $userId,
            'target' => $target,
            'cipher' => $cipher,
            'created_at' => now()->toIso8601String(),
            'uncertain' => false,
        ], self::SECRET_TTL_SECONDS);

        return ['id' => $id];
    }

    /**
     * @return array{admin_id:int,user_id:int,target:string,cipher:string,created_at:string,uncertain:bool}|null
     */
    public static function loadPending(string $id): ?array
    {
        $v = Cache::get(self::key($id));

        return is_array($v) ? $v : null;
    }

    public static function ownedBy(array $pending, int $adminId): bool
    {
        return (int) ($pending['admin_id'] ?? 0) === $adminId;
    }

    public static function decryptPassword(array $pending): ?string
    {
        try {
            $plain = Crypt::decryptString((string) $pending['cipher']);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return $plain !== '' ? $plain : null;
    }

    public static function consume(string $id): void
    {
        Cache::forget(self::key($id));
    }

    /**
     * @param  array{outcome:string,reason:?string,reference:?string,at:string}  $status
     */
    public static function storeStatus(string $id, int $adminId, int $userId, string $target, array $status): void
    {
        Cache::put(self::statusKey($id), array_merge($status, [
            'admin_id' => $adminId,
            'user_id' => $userId,
            'target' => $target,
        ]), self::STATUS_TTL_SECONDS);
    }

    /**
     * @return array|null
     */
    public static function loadStatus(string $id): ?array
    {
        $v = Cache::get(self::statusKey($id));

        return is_array($v) ? $v : null;
    }

    public static function currentAdminId(): int
    {
        return (int) Auth::id();
    }
}
