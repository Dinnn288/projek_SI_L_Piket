<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemporaryReporter extends Model
{
    protected $table = 'temporary_reporters';

    protected $fillable = [
        'class_id',
        'student_id',
        'active_date',
        'activated_by',
    ];

    protected function casts(): array
    {
        return [
            'active_date' => 'date',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(UserBaru::class, 'activated_by');
    }
}
