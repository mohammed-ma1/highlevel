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
            $table->text('lead_test_secret_key')->nullable()->after('lead_test_publishable_key');
            $table->text('lead_live_secret_key')->nullable()->after('lead_live_publishable_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['lead_test_secret_key', 'lead_live_secret_key']);
        });
    }
};
