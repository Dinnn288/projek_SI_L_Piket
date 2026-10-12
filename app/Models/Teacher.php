<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    protected $table = 'teachers';

    protected $fillable = [
        'user_id',
        'nip',
        'phone',
    ];

    public function userBaru(): BelongsTo
    {
        return $this->belongsTo(UserBaru::class, 'user_id');
    }

    /**
     * Kembalikan profil teachers milik akun guru.
     *
     * - Profil sudah ada (termasuk NIP resmi dari form): kembalikan apa
     *   adanya TANPA mengubah NIP.
     * - Profil belum ada: JANGAN membuat NIP dari username (berisiko
     *   bentrok dengan NIP guru lain dan menghasilkan NIP tidak resmi).
     *   Sebagai gantinya lempar MissingTeacherProfile agar pemanggil
     *   menanganinya secara terkendali (403 dengan pesan + arahkan ke
     *   Admin → Guru), bukan HTTP 500.
     * - Satu profil per akun dijamin UNIQUE DB teachers.user_id; fungsi
     *   ini hanya membaca, tidak pernah menulis.
     *
     * @throws \App\Exceptions\MissingTeacherProfile
     */
    public static function resolveForUser(UserBaru $user): self
    {
        $existing = $user->teacher()->first();

        if ($existing) {
            return $existing;
        }

        throw new \App\Exceptions\MissingTeacherProfile($user);
    }

    public function dutySchedules(): HasMany
    {
        return $this->hasMany(DutySchedule::class, 'teacher_id');
    }

    public function assignedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'assigned_teacher_id');
    }
}
