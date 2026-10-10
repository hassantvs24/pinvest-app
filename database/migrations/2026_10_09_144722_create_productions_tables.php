<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production entries: items consumed (components) plus extra costs
 * (labour) are converted into output items. Lines are stored per
 * production so stock and COGS can be computed live from confirmed
 * business events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('extra_cost', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->date('entry_date');
            $table->timestamps();

            $table->index('entry_date');
        });

        Schema::create('production_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 3, true);
            $table->timestamps();

            $table->index(['item_id', 'production_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_components');
        Schema::dropIfExists('productions');
    }
};
