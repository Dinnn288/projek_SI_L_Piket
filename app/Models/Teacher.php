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

    public function dutySchedules(): HasMany
    {
        return $this->hasMany(DutySchedule::class, 'teacher_id');
    }

    public function assignedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'assigned_teacher_id');
    }
}
