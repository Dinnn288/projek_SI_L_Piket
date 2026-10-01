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
     * Pastikan akun role guru selalu memiliki record teachers.
     *
     * Akun Guru yang dibuat via Admin → Users hanya memiliki record
     * users_baru tanpa record teachers pendamping. Method ini melengkapi
     * record tersebut secara idempoten (firstOrCreate agar tidak duplikat)
     * sehingga halaman Guru Piket tidak 403. NIP diisi dari username yang
     * unik per akun; Admin dapat memperbaikinya via Admin → Guru.
     */
    public static function resolveForUser(UserBaru $user): self
    {
        $existing = $user->teacher()->first();

        if ($existing) {
            return $existing;
        }

        return static::firstOrCreate(
            ['user_id' => $user->id],
            ['nip' => $user->username, 'phone' => null]
        );
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
