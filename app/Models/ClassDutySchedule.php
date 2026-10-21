<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassDutySchedule extends Model
{
    protected $table = 'class_duty_schedules';

    protected $fillable = [
        'class_id',
        'day',
    ];

    public const DAYS = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
