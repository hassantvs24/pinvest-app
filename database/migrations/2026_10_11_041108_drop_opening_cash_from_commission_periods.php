<?php

use App\Models\CommissionPeriod;
use App\Models\Investment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "opening cash" field is removed from cycle opening: it double-counted
 * the same physical money on every cycle. Any pre-existing opening cash is
 * converted into a regular Investment so the cash history is preserved,
 * then the column is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (CommissionPeriod::query()->whereNotNull('opening_cash')->get() as $period) {
            Investment::query()->create([
                'amount' => (float) $period->opening_cash,
                'note' => 'Opening cash of cycle #'.$period->id,
                'invested_at' => $period->opened_at->toDateString(),
            ]);
        }

        Schema::table('commission_periods', function (Blueprint $table): void {
            $table->dropColumn('opening_cash');
        });
    }

    public function down(): void
    {
        Schema::table('commission_periods', function (Blueprint $table): void {
            $table->decimal('opening_cash', 12, 2)->nullable();
        });
    }
};
