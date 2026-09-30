<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAttachment extends Model
{
    protected $table = 'report_attachments';

    protected $fillable = [
        'report_id',
        'file_patch',
        'original_name',
        'file_size',
        'mime_type',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }
}
