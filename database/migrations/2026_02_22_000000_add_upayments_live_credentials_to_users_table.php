<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Live mode credentials for UPayments (Production).
            $table->string('upayments_live_merchant_id')->nullable()->after('upayments_live_token');
            $table->text('upayments_live_api_key')->nullable()->after('upayments_live_merchant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'upayments_live_merchant_id',
                'upayments_live_api_key',
            ]);
        });
    }
};

