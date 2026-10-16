<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validasi nomor WhatsApp Indonesia + normalisasi ke format internasional.
 *
 * - Menerima 08..., +62..., 62..., 8... dengan pemisah umum (spasi, -, .).
 * - Menolak huruf dan simbol selain awalan +.
 * - Normalisasi: 08.. -> 62.., +62.. -> 62.., 8.. -> 62... (tanpa mengubah
 *   countryCode Fonnte yang juga default 62 — hasil idempoten karena tidak
 *   ada lagi angka 0 di depan).
 * - Hasil akhir: 62[28][0-9]{7,12} (total 10-15 digit).
 */
class IndonesianWhatsappNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail('Nomor WhatsApp tidak valid. Gunakan format 08xxxxxxxxxx.');
        }
    }

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $v = trim($value);

        if ($v === '' || strlen($v) > 25) {
            return null;
        }

        // Hanya digit, spasi, dan pemisah umum; awalan + diizinkan.
        if (! (bool) preg_match('/\A\+?[0-9][0-9\s\-.]*\z/', $v)) {
            return null;
        }

        $digits = (string) preg_replace('/[^0-9]/', '', $v);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        // 62 + [2|8] + 7-12 digit (total 10-15 digit).
        if (! (bool) preg_match('/\A62[28][0-9]{7,12}\z/', $digits)) {
            return null;
        }

        return $digits;
    }
}
