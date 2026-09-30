<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'major_id',
        'name',
        'status',
    ];

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class, 'major_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function classRepresentatives(): HasMany
    {
        return $this->hasMany(ClassRepresentative::class, 'class_id');
    }

    public function temporaryReporters(): HasMany
    {
        return $this->hasMany(TemporaryReporter::class, 'class_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'class_id');
    }
}
