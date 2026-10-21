<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassDutyReport extends Model
{
    protected $table = 'class_duty_reports';

    public const STATUS_MENUNGGU = 'menunggu_verifikasi';

    public const STATUS_PERBAIKI = 'perlu_diperbaiki';

    public const STATUS_DISETUJUI = 'disetujui';

    protected $fillable = [
        'class_id',
        'report_date',
        'student_id',
        'title',
        'checklist',
        'notes',
        'status',
        'verified_by_teacher_id',
        'verified_at',
        'return_note',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'checklist' => 'array',
            'verified_at' => 'datetime',
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

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'verified_by_teacher_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ClassDutyReportHistory::class, 'class_duty_report_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClassDutyReportAttachment::class, 'class_duty_report_id');
    }
}
