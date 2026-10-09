<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock losses: stock that vanished without a sale (theft, rot,
 * damage, shrinkage). Each loss targets one item and follows the same
 * pending → owner-confirms flow as other entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_losses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('note')->nullable();
            $table->date('entry_date');
            $table->string('status')->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_losses');
    }
};
