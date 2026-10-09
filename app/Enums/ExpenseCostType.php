<?php

namespace App\Enums;

/**
 * How an expense head affects profit: general expenses reduce profit
 * immediately; product costs (transport, labour, processing) are added
 * to stock value and only leave the business as COGS when the linked
 * goods are sold.
 */
enum ExpenseCostType: string
{
    case General = 'general';
    case Product = 'product';

    public function addsToStock(): bool
    {
        return $this === self::Product;
    }
}
