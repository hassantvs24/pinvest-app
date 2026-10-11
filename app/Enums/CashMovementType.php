<?php

namespace App\Enums;

/**
 * Type of a partner cash movement. `hold` ADDS to the cash held by the
 * partner; the others subtract from it. `commission_adjustment` also
 * settles the linked commission settlement.
 */
enum CashMovementType: string
{
    case Hold = 'hold';
    case Return = 'return';
    case Spent = 'spent';
    case CommissionAdjustment = 'commission_adjustment';

    /**
     * Whether this movement increases the partner's outstanding cash.
     */
    public function addsToOutstanding(): bool
    {
        return $this === self::Hold;
    }
}
