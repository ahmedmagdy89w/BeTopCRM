<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    public const CACHE_KEY = 'company_settings.all';

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $record = static::where('key', $key)->first();

        return $record ? $record->value : $default;
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::forgetCache();
    }

    public static function setMany(array $keyValues): void
    {
        foreach ($keyValues as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        static::forgetCache();
    }

    public static function getAllAsArray(): array
    {
        return static::all()->pluck('value', 'key')->all();
    }

    /**
     * Cached read of all settings. Busted automatically on every
     * write through setValue()/setMany() — call forgetCache() from
     * any code path that writes/deletes rows directly.
     *
     * @return array<string, mixed>
     */
    public static function cachedAll(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::getAllAsArray());
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
