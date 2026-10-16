<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Penyajian lampiran laporan yang aman (B3.5).
 *
 * Menutup dua temuan audit B3.2 tanpa mengubah arsitektur, workflow,
 * endpoint, tampilan, maupun otorisasi yang sudah berjalan:
 * - F07 path traversal via `file_patch`: path dikungkung di direktori
 *   `reports/` disk privat (`local`) memakai validasi sintaks +
 *   `realpath()` + verifikasi prefix root + file reguler.
 * - F08 header respons: MIME dari deteksi isi file (bukan nama asli
 *   pengguna), allowlist gambar, `nosniff`, `Content-Disposition`
 *   eksplisit inline agar foto sah tetap tampil di UI.
 *
 * Otorisasi (laporan induk + `attachment.report_id`) tetap di controller.
 * Kegagalan apa pun → null (pemanggil merespons 404 generik tanpa
 * membocorkan path server).
 */
final class SecureAttachment
{
    public const DIRECTORY = 'reports';

    private const MAX_PATH_LENGTH = 255;

    /**
     * MIME yang boleh disajikan inline sebagai foto laporan, dipetakan ke
     * ekstensi kanonis untuk nama file respons.
     *
     * @var array<string, string>
     */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Resolusi aman `file_patch` menjadi path absolut + MIME terdeteksi.
     *
     * @return array{path: string, mime: string}|null
     */
    public static function resolve(?string $storedPath): ?array
    {
        if (! is_string($storedPath) || $storedPath === '' || strlen($storedPath) > self::MAX_PATH_LENGTH) {
            return null;
        }

        // Byte null, pemisah Windows, dan path absolut (Unix/drive/UNC).
        if (str_contains($storedPath, "\0") || str_contains($storedPath, '\\')) {
            return null;
        }

        if (str_starts_with($storedPath, '/') || (bool) preg_match('#\A[a-zA-Z]:#', $storedPath)) {
            return null;
        }

        // Harus relatif di bawah prefix direktori laporan.
        if (! str_starts_with($storedPath, self::DIRECTORY.'/')) {
            return null;
        }

        $rest = substr($storedPath, strlen(self::DIRECTORY) + 1);

        if ($rest === '') {
            return null;
        }

        // Tolak segmen kosong, `.`, dan `..` (prefix string saja tidak cukup).
        foreach (explode('/', $rest) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        $disk = Storage::disk('local');
        $candidate = $disk->path($storedPath);

        // Symlink pada kandidat tidak pernah sah (store() tidak membuatnya).
        if (is_link($candidate)) {
            return null;
        }

        // Normalisasi + verifikasi lokasi aktual (menutup symlink di tengah
        // path yang lolos cek sintaks).
        $real = realpath($candidate);

        if ($real === false) {
            return null;
        }

        $rootReal = realpath($disk->path(self::DIRECTORY));

        if ($rootReal === false) {
            return null;
        }

        if (! str_starts_with($real, $rootReal.DIRECTORY_SEPARATOR)) {
            return null;
        }

        // Harus file reguler (menolak direktori, FIFO, socket, device).
        if (! is_file($real)) {
            return null;
        }

        // MIME dari isi file (fileinfo), bukan dari nama asli pengguna
        // maupun kolom `mime_type` yang dipengaruhi input klien.
        $mime = @mime_content_type($real);

        if (! is_string($mime) || ! isset(self::ALLOWED_MIME[$mime])) {
            return null;
        }

        return ['path' => $real, 'mime' => $mime];
    }

    /**
     * Respons file inline dengan header keamanan.
     *
     * @param  array{path: string, mime: string}  $resolved
     */
    public static function response(array $resolved): BinaryFileResponse
    {
        // Filename respons diturunkan dari basename aman + ekstensi kanonis
        // MIME terdeteksi (bukan nama asli pengguna) agar tidak ada injeksi
        // header maupun eksekusi bertipe salah.
        $base = pathinfo($resolved['path'], PATHINFO_FILENAME);
        $safe = (string) preg_replace('/[^A-Za-z0-9_-]/', '_', $base);
        $safe = substr($safe !== '' ? $safe : 'lampiran', 0, 100);
        $name = $safe.'.'.self::ALLOWED_MIME[$resolved['mime']];

        return response()->file($resolved['path'], [
            'Content-Type' => $resolved['mime'],
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
