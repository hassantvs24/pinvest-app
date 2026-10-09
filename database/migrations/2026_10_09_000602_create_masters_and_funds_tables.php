<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data tables (owner-managed dropdowns) + owner fund tables.
     */
    public function up(): void
    {
        Schema::create('expense_heads', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('unit')->default('pcs'); // kg, gram, tola, pcs, ml
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('default_price', 10, 2)->default(0);
            $table->string('unit')->default('pcs'); // kg, gram, tola, pcs, ml
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('investments', function (Blueprint $table): void {
            $table->id();
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->date('invested_at');
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->date('payout_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('investments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('expense_heads');
        // unit column drops with the tables
    }
};
