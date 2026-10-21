<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassDutyReportAttachment extends Model
{
    protected $table = 'class_duty_report_attachments';

    protected $fillable = [
        'class_duty_report_id',
        'file_patch',
        'original_name',
        'file_size',
        'mime_type',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ClassDutyReport::class, 'class_duty_report_id');
    }
}
