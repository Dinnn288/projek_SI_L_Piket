<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Penanganan pelanggaran UNIQUE teachers.nip (B2.2-H2).
 *
 * Dipakai di batas transaksi TeacherController/UserController store+update.
 * Hanya mengonversi pelanggaran yang TERIDENTIFIKASI sebagai duplikat NIP:
 * SQLSTATE 23000 + driver code 1062 + pesan memuat nama indeks
 * `teachers_nip_unique`. Pelanggaran lain (username, NIS, FK, dsb.)
 * dilempar kembali ke penanganan Laravel — tidak pernah disamarkan
 * menjadi error NIP, dan SQL mentah tidak ditampilkan ke pengguna.
 */
trait HandlesNipUniqueViolation
{
    public static function isNipUniqueViolation(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        // SQLSTATE integritas: string '23000' (PDO) atau int 23000.
        if ((string) $e->getCode() !== '23000' && (int) $e->getCode() !== 23000) {
            return false;
        }

        $info = $e->errorInfo ?? [];

        // Driver code MySQL duplicate entry: 1062 (posisi [1]).
        if (! isset($info[1]) || (int) $info[1] !== 1062) {
            return false;
        }

        // Nama indeks harus disebut di pesan driver agar tidak salah
        // mengklaim constraint lain (username/NIS/FK) sebagai NIP.
        $message = (string) ($info[2] ?? $e->getMessage());

        return str_contains($message, 'teachers_nip_unique');
    }

    /**
     * Jalankan callback dalam transaksi; konversi pelanggaran NIP menjadi
     * error validasi field `nip`. Respons mengikuti jenis request:
     * JSON → 422 {message, errors.nip}; web → back + error + input lama.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T|RedirectResponse|JsonResponse
     *
     * @throws \Throwable
     */
    public function transactionalNipSafe(\Closure $callback, string $nipMessage = 'NIP sudah digunakan.')
    {
        try {
            return DB::transaction($callback);
        } catch (QueryException $e) {
            if (! static::isNipUniqueViolation($e)) {
                throw $e;
            }

            report($e);

            throw ValidationException::withMessages(['nip' => $nipMessage]);
        }
    }
}
