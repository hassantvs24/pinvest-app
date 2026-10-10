<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Central date/time display formats so Blade and PHP render identically.
 * Month names are localized: English for "en", Bengali month names for
 * the Bengali interface.
 */
class DateFormats
{
    public const DATE = 'd M Y';

    public const TIME = 'h:i A';

    /**
     * Bengali month names keyed by English short name (Jan..Dec).
     *
     * @var array<string, string>
     */
    private const BENGALI_MONTHS = [
        'Jan' => 'জানু', 'Feb' => 'ফেব্রু', 'Mar' => 'মার্চ', 'Apr' => 'এপ্রি',
        'May' => 'মে', 'Jun' => 'জুন', 'Jul' => 'জুলা', 'Aug' => 'আগ',
        'Sep' => 'সেপ্টে', 'Oct' => 'অক্টো', 'Nov' => 'নভে', 'Dec' => 'ডিসে',
    ];

    /**
     * Format a date with the app's display format, translating month
     * names when the active locale is Bengali.
     */
    public static function date(Carbon $date): string
    {
        $formatted = $date->format(self::DATE);

        if (app()->getLocale() !== 'bn') {
            return $formatted;
        }

        return strtr($formatted, self::BENGALI_MONTHS);
    }
}
