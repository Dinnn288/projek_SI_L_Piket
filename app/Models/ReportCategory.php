<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCategory extends Model
{
    protected $table = 'report_categories';

    protected $fillable = [
        'name',
        'description',
        'status',
        'priority',
    ];

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'category_id');
    }
}
