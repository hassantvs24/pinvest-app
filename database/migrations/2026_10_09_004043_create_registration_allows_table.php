<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner-approved phone numbers / emails that may register once
     * as a partner account.
     */
    public function up(): void
    {
        Schema::create('registration_allows', function (Blueprint $table): void {
            $table->id();
            $table->string('phone')->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_allows');
    }
};
