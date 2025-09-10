<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('lead_live_api_key')->nullable();
            $table->text('lead_live_publishable_key')->nullable();
            $table->text('lead_test_api_key')->nullable();
            $table->text('lead_test_publishable_key')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'lead_live_api_key',
                'lead_live_publishable_key',
                'lead_test_api_key',
                'lead_test_publishable_key',
            ]);
        });
    }
};