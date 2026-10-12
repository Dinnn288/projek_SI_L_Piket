<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Idempotensi pembuatan laporan siswa (B1) tanpa skema baru.
 *
 * - Key dari klien: UUID per render form (`idempotency_key`), tervalidasi
 *   format di controller, terikat ke user via prefix key.
 * - Proteksi race: `Cache::lock("...:lock", 60)->block(10)` memakai
 *   DatabaseLock (insert unik atomik ke tabel `cache_locks` bawaan
 *   Laravel — tabel sudah ada dari migrasi cache, bukan tabel baru).
 * - Hasil: setelah transaksi sukses, simpan `report_id` ke cache
 *   (TTL 24 jam). Retry dengan key sama → replay redirect ke laporan
 *   yang sama tanpa membuat laporan/lampiran/notifikasi baru.
 * - Payload berbeda dengan key sama → ditolak 422 (tidak diam-diam
 *   dianggap pengiriman baru).
 * - Transaksi gagal → marker tidak disimpan → retry boleh membuat baru;
 *   lock selalu dilepas di `finally`.
 */
class ReportIdempotency
{
    public const RESULT_TTL_SECONDS = 86400;

    public const LOCK_SECONDS = 60;

    public const LOCK_WAIT_SECONDS = 10;

    public static function key(int|string $userId, string $key): string
    {
        return 'report_submit:user:'.$userId.':'.$key;
    }

    public static function lockKey(int|string $userId, string $key): string
    {
        return self::key($userId, $key).':lock';
    }

    public static function validKey(?string $key): bool
    {
        if (! is_string($key) || $key === '') {
            return false;
        }

        return (bool) preg_match('/\A[0-9a-fA-F-]{8,64}\z/', $key);
    }

    /**
     * Sidik payload untuk deteksi key dipakai-ulang dengan isi berbeda.
     * Hanya field teks + nama/ukuran file (bukan isi biner).
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fingerprint(array $validated, ?array $photosMeta = null): string
    {
        return hash('sha256', json_encode([
            'title' => (string) ($validated['title'] ?? ''),
            'category_id' => (int) ($validated['category_id'] ?? 0),
            'location' => (string) ($validated['location'] ?? ''),
            'description' => (string) ($validated['description'] ?? ''),
            'photos' => $photosMeta ?? [],
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{report_id: int, fingerprint: string}|null
     */
    public static function result(int|string $userId, string $key): ?array
    {
        $value = Cache::get(self::key($userId, $key));

        if (! is_array($value) || ! isset($value['report_id'], $value['fingerprint'])) {
            return null;
        }

        return ['report_id' => (int) $value['report_id'], 'fingerprint' => (string) $value['fingerprint']];
    }

    public static function store(int|string $userId, string $key, int $reportId, string $fingerprint): void
    {
        Cache::put(
            self::key($userId, $key),
            ['report_id' => $reportId, 'fingerprint' => $fingerprint],
            self::RESULT_TTL_SECONDS
        );
    }

    public static function lock(int|string $userId, string $key): \Illuminate\Contracts\Cache\Lock
    {
        return Cache::lock(self::lockKey($userId, $key), self::LOCK_SECONDS);
    }
}
