<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Pinvest-specific columns to the default users table.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Phone-only partners have no email, so the column must be nullable.
            $table->string('email')->nullable()->change();
            $table->string('phone')->unique()->after('email');
            $table->string('role')->default('partner')->after('phone');
            $table->decimal('commission_rate', 5, 2)->default(0)->after('role');
            $table->string('preferred_language', 2)->nullable()->after('commission_rate');
            $table->boolean('is_active')->default(true)->after('preferred_language');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'role', 'commission_rate', 'preferred_language', 'is_active']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
