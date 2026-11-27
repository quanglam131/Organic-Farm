<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime settings the administrator can change (Tuần 2: "cấu hình cảnh báo xả hàng").
 * Values are cached; DEFAULTS lists every setting with its fallback value.
 */
class Setting extends Model
{
    public const DEFAULTS = [
        // A batch this many days (or fewer) from expiry raises a red alert for Sales.
        'expiry_alert_days' => 2,
        // Suggested flash-sale discount for those batches, shown next to the alert.
        'flash_sale_discount_percent' => 30,
    ];

    protected $fillable = ['key', 'value'];

    public static function get(string $key): mixed
    {
        $default = self::DEFAULTS[$key] ?? null;

        $value = Cache::rememberForever('setting.'.$key, fn () => static::where('key', $key)->value('value'));

        if ($value === null) {
            return $default;
        }

        return is_int($default) ? (int) $value : $value;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget('setting.'.$key);
    }

    /** @return array<string, mixed> Every setting with its current value. */
    public static function current(): array
    {
        return collect(self::DEFAULTS)->keys()->mapWithKeys(fn ($key) => [$key => self::get($key)])->all();
    }
}
