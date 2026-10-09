<?php

namespace App;

/**
 * Status of a partner entry. Only confirmed entries count in totals.
 */
enum EntryStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
