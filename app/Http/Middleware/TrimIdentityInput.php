<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Normalisasi trim identitas (B2.1) — terbatas pada field identitas.
 *
 * - username, nis, nip: hapus spasi awal/akhir SEBELUM validasi agar
 *   validasi unik memeriksa nilai yang benar-benar disimpan.
 * - Kapitalisasi dipertahankan (tanpa upper/lower); password TIDAK
 *   disentuh; field lain (name, location, dsb.) TIDAK diubah.
 * - Terdaftar pada grup web sehingga berlaku konsisten untuk login,
 *   form Users, form Siswa, dan form Guru.
 */
class TrimIdentityInput
{
    /** @var list<string> */
    public const FIELDS = ['username', 'nis', 'nip'];

    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->request->all();

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $input) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }

        $request->request->replace($input);

        return $next($request);
    }
}
