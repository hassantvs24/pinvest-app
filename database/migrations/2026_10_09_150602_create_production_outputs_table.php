<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A production run can yield several items (e.g. cutting one wood lot
 * yields chips plus scrap). Output lines live in their own table; the
 * run's cost pool is split across them by sale value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_outputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index(['item_id', 'production_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_outputs');
    }
};
