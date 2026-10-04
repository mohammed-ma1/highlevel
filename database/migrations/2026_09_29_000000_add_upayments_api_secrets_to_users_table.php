<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('upayments_test_api_secret')->nullable()->after('upayments_test_token');
            $table->text('upayments_live_api_secret')->nullable()->after('upayments_live_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'upayments_test_api_secret',
                'upayments_live_api_secret',
            ]);
        });
    }
};
