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
            $table->string('upayments_mode')->nullable(); // test|live
            $table->text('upayments_test_token')->nullable();
            $table->text('upayments_live_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'upayments_mode',
                'upayments_test_token',
                'upayments_live_token',
            ]);
        });
    }
};

