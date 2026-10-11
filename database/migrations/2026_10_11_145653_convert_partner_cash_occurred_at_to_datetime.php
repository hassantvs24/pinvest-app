<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partner cash movements become exact moments (date + time), matching
 * the entry/cycle datetime convention: a movement records the moment it
 * happened, so cycle boundaries can scope them to the exact instant.
 * Existing date values become midnight of that day.
 *
 * doctrine/dbal is not installed, so ->change() is unavailable; MySQL
 * columns are altered with raw SQL (data-preserving). SQLite stores
 * dates as plain text ('Y-m-d'), which reads as midnight and compares
 * correctly against full datetimes, so no structural change is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE partner_cash_movements MODIFY occurred_at DATETIME NOT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE partner_cash_movements MODIFY occurred_at DATE NOT NULL');
    }
};
