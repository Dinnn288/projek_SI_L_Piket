<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassDutyReportHistory extends Model
{
    protected $table = 'class_duty_report_histories';

    public const UPDATED_AT = null;

    protected $fillable = [
        'class_duty_report_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'note',
        'created_at',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ClassDutyReport::class, 'class_duty_report_id');
    }

    public function userBaru(): BelongsTo
    {
        return $this->belongsTo(UserBaru::class, 'user_id');
    }
}
