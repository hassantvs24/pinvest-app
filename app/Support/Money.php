<?php

namespace App\Support;

/**
 * Single source of truth for money display: currency symbol + grouping
 * + two decimals. Blades render money only through this class so a
 * future currency change touches one place.
 */
class Money
{
    public const SYMBOL = '৳';

    public static function format(float $amount): string
    {
        return self::SYMBOL.number_format($amount, 2);
    }
}
