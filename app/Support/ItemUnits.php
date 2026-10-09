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
     * Validation rule fragment, e.g. "in:kg,gram,tola,pcs,ml".
     */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::OPTIONS);
    }
}
