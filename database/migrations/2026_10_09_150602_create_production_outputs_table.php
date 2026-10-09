<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A production run can yield several finished goods (e.g. melting an
 * old ornament yields pure gold plus scrap). Outputs move from the
 * productions header into their own table; existing rows are
 * backfilled one-to-one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_outputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index(['sale_item_id', 'production_id']);
        });

        // Backfill one output line per existing production.
        DB::statement(
            'INSERT INTO production_outputs (production_id, sale_item_id, quantity, created_at, updated_at) '
            .'SELECT id, sale_item_id, quantity, created_at, updated_at FROM productions'
        );

        // Order matters across drivers: drop the foreign key first (MySQL
        // blocks dropping an index a FK needs), then the composite index
        // (SQLite blocks dropping a column an index covers), then columns.
        Schema::table('productions', function (Blueprint $table): void {
            $table->dropForeign(['sale_item_id']);
            $table->dropIndex('productions_sale_item_id_entry_date_index');
            $table->dropColumn(['sale_item_id', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table): void {
            $table->foreignId('sale_item_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0)->after('sale_item_id');
        });

        DB::statement(
            'UPDATE productions SET sale_item_id = (SELECT sale_item_id FROM production_outputs WHERE production_outputs.production_id = productions.id), '
            .'quantity = (SELECT quantity FROM production_outputs WHERE production_outputs.production_id = productions.id)'
        );

        Schema::dropIfExists('production_outputs');
    }
};
