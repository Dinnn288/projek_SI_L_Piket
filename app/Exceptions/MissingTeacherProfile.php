<?php

namespace App\Exceptions;

use App\Models\UserBaru;
use RuntimeException;

/**
 * Profil teachers belum ada untuk akun guru.
 *
 * Dilempar oleh Teacher::resolveForUser() sebagai pengganti pembuatan
 * NIP otomatis dari username (B2.1: tanpa NIP palsu, tanpa format
 * karangan). Pemanggil menanganinya sebagai 403 terkendali dengan pesan
 * yang mengarahkan ke Admin → Guru untuk melengkapi NIP resmi.
 */
class MissingTeacherProfile extends RuntimeException
{
    public readonly int $userId;

    public readonly string $username;

    public function __construct(UserBaru $user)
    {
        $this->userId = (int) $user->id;
        $this->username = (string) $user->username;

        parent::__construct(
            'Profil guru untuk akun "'.$this->username.'" belum dilengkapi. Hubungi Admin (Data Guru) untuk melengkapi NIP resmi.'
        );
    }
}
