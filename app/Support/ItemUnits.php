<?php

namespace App\Support;

/**
 * Single source of truth for item units (purchase/sale items).
 */
class ItemUnits
{
    /**
     * @var array<int, string>
     */
    public const OPTIONS = ['kg', 'gram', 'tola', 'pcs', 'ml'];

    /**
     * Unit options for dropdowns: value => translated label.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::OPTIONS)
            ->mapWithKeys(fn (string $unit) => [$unit => __("messages.unit_{$unit}")])
            ->all();
    }

    /**
     * Translated label for one unit (falls back to the raw value).
     */
    public static function label(?string $unit): string
    {
        if (! $unit) {
            return '';
        }

        return __("messages.unit_{$unit}") === "messages.unit_{$unit}"
            ? $unit
            : __("messages.unit_{$unit}");
    }

    /**
     * @var array<string, string> unit => category (weight shares one base).
     */
    private const CATEGORIES = [
        'kg' => 'weight',
        'gram' => 'weight',
        'tola' => 'weight',
        'pcs' => 'count',
        'ml' => 'volume',
    ];

    /**
     * Category of a unit: 'weight', 'count' or 'volume'. Units of
     * different categories can never be converted into each other.
     */
    public static function category(string $unit): string
    {
        return self::CATEGORIES[$unit] ?? 'count';
    }

    /**
     * Whether two units can be linked across purchase/sale items
     * (they must share the same category).
     */
    public static function compatible(string $a, string $b): bool
    {
        return self::category($a) === self::category($b);
    }

    /**
     * Convert a quantity to the base unit of its category: grams for
     * weight units, the raw quantity for count/volume units. Factors
     * come from config/inventory.php — nothing is hardcoded here.
     */
    public static function toBase(float $quantity, string $unit): float
    {
        if (self::category($unit) !== 'weight') {
            return $quantity;
        }

        return $quantity * (float) config('inventory.grams_per_unit.'.$unit, 1.0);
    }

    /**
     * Convert a base-quantity back into the given unit for display.
     */
    public static function fromBase(float $baseQuantity, string $unit): float
    {
        if (self::category($unit) !== 'weight') {
            return $baseQuantity;
        }

        return $baseQuantity / (float) config('inventory.grams_per_unit.'.$unit, 1.0);
    }

    /**
     * Format a quantity for display: up to 3 decimals, trailing zeros
     * trimmed (2.500 → "2.5", 12 → "12").
     */
    public static function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3), '0'), '.');
    }

    /**
     * Validation rule fragment, e.g. "in:kg,gram,tola,pcs,ml".
     */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::OPTIONS);
    }
}
