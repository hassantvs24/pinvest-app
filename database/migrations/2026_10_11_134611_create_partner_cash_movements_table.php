<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner cash custody ledger: tracks money that physically sits with a
 * partner (sales money kept, cash the owner hands over, incl. investment
 * money transferred for daily operations). Cash in hand is NOT changed
 * by these rows — the money still belongs to the business; only custody
 * moves. Types: hold (+) / return, spent, commission_adjustment (−).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('hold'); // hold / return / spent / commission_adjustment
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->date('occurred_at');
            $table->foreignId('commission_settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_cash_movements');
    }
};
