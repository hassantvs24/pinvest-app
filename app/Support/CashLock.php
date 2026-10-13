<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Serializes every cash-affecting operation (owner withdrawals, payout
 * approvals) so two of them can never read the same cash-in-hand figure
 * and both pass the "never overdraw" check.
 *
 * MySQL has no row to lock for an aggregate SUM, so an advisory named
 * lock is used. SQLite already serializes writers at the database level
 * (single active writer), where the surrounding transaction is enough.
 */
class CashLock
{
    private const LOCK_NAME = 'pinvest_cash_lock';

    /**
     * Acquire the global cash lock. Blocks up to $timeoutSeconds.
     */
    public static function acquire(int $timeoutSeconds = 10): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS ok', [self::LOCK_NAME, $timeoutSeconds]);

        if ((int) ($result->ok ?? 0) !== 1) {
            throw new RuntimeException('Could not acquire the cash lock.');
        }
    }

    /**
     * Release the global cash lock (also released automatically when the
     * connection closes — releasing is belt-and-braces for pooled ones).
     */
    public static function release(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
    }
}
