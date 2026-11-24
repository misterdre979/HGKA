<?php

namespace App\Support;

use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Cache;

class Settings
{
    public const DEFAULTS = [
        'school_name' => 'His Grace Kiddies Academy',
        'school_short_name' => 'HGKA',
        'motto' => 'Raising Godly Excellence',
        'address' => '',
        'phone' => '',
        'email' => '',
        'currency' => 'NGN',
        'currency_symbol' => '₦',
        'timezone' => 'Africa/Lagos',
        'primary_color' => '#102B50',
        'secondary_color' => '#246BCE',
        'accent_color' => '#E5A52B',
        'logo_path' => '',
        'max_ca_score' => '40',
        'max_exam_score' => '60',
        'library_loan_days' => '14',
        'library_fine_per_day' => '50',
        'notify_parents_on_absence' => '1',
        'principal_name' => '',
        'next_term_begins' => '',
        'low_stock_notify' => '1',
    ];

    /** Keys that may be shown on the public login page. */
    public const PUBLIC_KEYS = [
        'school_name', 'school_short_name', 'motto', 'primary_color', 'secondary_color', 'accent_color',
        'logo_path', 'currency', 'currency_symbol',
    ];

    public static function all(): array
    {
        return Cache::remember('hgka.settings', 600, fn () => array_merge(
            self::DEFAULTS,
            SchoolSetting::pluck('value', 'key')->map(fn ($v) => $v ?? '')->all()
        ));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function float(string $key, float $default = 0): float
    {
        return (float) self::get($key, $default);
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            SchoolSetting::updateOrCreate(['key' => $key], ['value' => $value === null ? '' : (string) $value]);
        }
        Cache::forget('hgka.settings');
    }
}
