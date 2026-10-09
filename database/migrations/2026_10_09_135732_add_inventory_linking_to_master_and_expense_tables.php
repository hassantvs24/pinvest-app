<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory/COGS linking:
 * - sale items know which purchase item they resell (stock source)
 * - expense heads are general (period cost) or product (stock cost)
 * - product expenses may optionally be tied to one purchase item
 * Sale items are backfilled by exact name match with purchase items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreignId('purchase_item_id')->nullable()->after('default_price')->constrained('purchase_items')->nullOnDelete();
        });

        Schema::table('expense_heads', function (Blueprint $table): void {
            $table->string('cost_type')->default('general')->after('name');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('purchase_item_id')->nullable()->after('expense_head_id')->constrained('purchase_items')->nullOnDelete();
        });

        // Backfill: link sale items to purchase items with the same name.
        DB::statement(
            'UPDATE sale_items SET purchase_item_id = '
            .'(SELECT id FROM purchase_items WHERE purchase_items.name = sale_items.name) '
            .'WHERE EXISTS (SELECT id FROM purchase_items WHERE purchase_items.name = sale_items.name)'
        );
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_item_id');
        });

        Schema::table('expense_heads', function (Blueprint $table): void {
            $table->dropColumn('cost_type');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_item_id');
        });
    }
};
