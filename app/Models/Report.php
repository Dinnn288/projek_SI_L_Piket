<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $table = 'reports';

    protected $fillable = [
        'student_id',
        'class_id',
        'category_id',
        'title',
        'description',
        'location',
        'priority',
        'status',
        'assigned_teacher_id',
        'assigned_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReportCategory::class, 'category_id');
    }

    public function assignedTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'assigned_teacher_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class, 'report_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ReportHistory::class, 'report_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'report_id');
    }
}
