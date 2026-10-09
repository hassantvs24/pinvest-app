<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productions follow the same confirmation flow as other entries:
 * partners create them as pending, the owner confirms. Existing rows
 * default to confirmed so history stays intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table): void {
            $table->string('status')->default('confirmed')->after('entry_date');
            $table->foreignId('confirmed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['status', 'confirmed_at']);
        });
    }
};
