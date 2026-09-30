<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportHistory extends Model
{
    protected $table = 'report_histories';

    public const UPDATED_AT = null;

    protected $fillable = [
        'report_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'note',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function userBaru(): BelongsTo
    {
        return $this->belongsTo(UserBaru::class, 'user_id');
    }
}
