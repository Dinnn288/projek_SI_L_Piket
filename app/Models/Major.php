<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Major extends Model
{
    protected $table = 'majors';

    protected $fillable = [
        'name',
        'status',
    ];

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'major_id');
    }
}
