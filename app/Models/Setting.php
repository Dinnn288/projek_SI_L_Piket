<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::where('key', $key)->value('value');

        return $value !== null ? (string) $value : $default;
    }

    public static function set(string $key, ?string $value): self
    {
        return tap(static::firstOrNew(['key' => $key]), function (self $setting) use ($value) {
            $setting->value = $value;
            $setting->save();
        });
    }
}
