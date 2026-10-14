<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Simple key/value company settings (work start time, SST rate, quotation terms…).
 */
class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public const DEFAULTS = [
        'company_name' => 'DEMO COMPANY SDN BHD',
        'company_reg_no' => '000000-X',
        'work_start' => '08:30',
        'late_grace_minutes' => '10',
        'sst_rate' => '0',
        'quotation_validity_days' => '30',
        'invoice_due_days' => '30',
        'quotation_terms' => "1. 50% deposit upon confirmation, balance upon completion.\n2. Quotation valid for 30 days.\n3. Warranty as stated in the warranty certificate.",
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }
}
