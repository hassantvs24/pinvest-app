<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entry moments and cycle boundaries become datetimes so a new cycle can
 * open the same day a previous one closed (boundary = the exact open/
 * close moment). Existing date values become midnight of that day, so
 * all previously closed cycles keep accounting for exactly the days
 * they used to.
 *
 * doctrine/dbal is not installed, so ->change() is unavailable; MySQL
 * columns are altered with raw SQL (data-preserving). SQLite stores
 * dates as plain text ('Y-m-d'), which reads as midnight and compares
 * correctly against full datetimes, so no structural change is needed.
 */
return new class extends Migration
{
    /**
     * Columns to convert, per table.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'purchases' => ['entry_date'],
        'sales' => ['entry_date'],
        'expenses' => ['entry_date'],
        'productions' => ['entry_date'],
        'stock_losses' => ['entry_date'],
        'commission_periods' => ['opened_at', 'closed_at'],
        'commission_settlements' => ['period_start', 'period_end'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $nullable = $column === 'closed_at' ? 'NULL' : 'NOT NULL';

                DB::statement("ALTER TABLE {$table} MODIFY {$column} DATETIME {$nullable}");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $nullable = $column === 'closed_at' ? 'NULL' : 'NOT NULL';

                DB::statement("ALTER TABLE {$table} MODIFY {$column} DATE {$nullable}");
            }
        }
    }
};
