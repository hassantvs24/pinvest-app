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

    /**
     * Unit price derived from a total and a quantity, rounded to the
     * same two decimals the purchase/sale columns store.
     */
    public static function unitPrice(float $total, float $quantity): float
    {
        return round($total / $quantity, 2);
    }
}
