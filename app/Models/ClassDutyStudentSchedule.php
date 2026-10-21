<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassDutyStudentSchedule extends Model
{
    protected $table = 'class_duty_student_schedules';

    protected $fillable = [
        'class_id',
        'student_id',
        'day',
    ];

    public const DAYS = ['senin', 'selasa', 'rabu', 'kamis', 'jumat'];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}