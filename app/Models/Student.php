<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $table = 'students';

    protected $fillable = [
        'user_id',
        'nis',
        'class_id',
        'phone',
    ];

    public function userBaru(): BelongsTo
    {
        return $this->belongsTo(UserBaru::class, 'user_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function classRepresentatives(): HasMany
    {
        return $this->hasMany(ClassRepresentative::class, 'student_id');
    }

    public function temporaryReporters(): HasMany
    {
        return $this->hasMany(TemporaryReporter::class, 'student_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'student_id');
    }
}
