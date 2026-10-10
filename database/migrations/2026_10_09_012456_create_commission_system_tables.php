<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commission cycle system: monthly settlements per partner based on
     * period net profit x partner rate, payout requests, owner profit
     * withdrawals and app settings.
     */
    public function up(): void
    {
        // Owner-managed commission periods: owner opens a period (with
        // opening cash/investment) and closes it manually; profit/loss and
        // commissions are computed at closing time.
        Schema::create('commission_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->nullable();
            $table->date('opened_at');
            $table->decimal('opening_cash', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('open'); // open / closed
            $table->decimal('profit', 12, 2)->nullable();
            $table->date('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('opened_at');
        });

        Schema::create('commission_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_period_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('business_profit', 12, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->timestamps();

            // One settlement per partner per period, ever.
            $table->unique(['user_id', 'commission_period_id']);
            $table->index('status');
        });

        Schema::create('payout_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('owner_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->date('withdrawn_at');
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('commission_periods');
        Schema::dropIfExists('owner_withdrawals');
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('commission_settlements');
    }
};
