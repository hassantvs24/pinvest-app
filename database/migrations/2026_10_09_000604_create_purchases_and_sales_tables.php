<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partner purchase and sale entries (pending until owner confirms).
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3, true); // fractional weights allowed (e.g. 1.5 tola)
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->text('note')->nullable();
            $table->date('entry_date');
            $table->string('status')->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('entry_date');
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3, true);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->text('note')->nullable();
            $table->date('entry_date');
            $table->string('status')->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
            $table->index('entry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
        Schema::dropIfExists('purchases');
    }
};
