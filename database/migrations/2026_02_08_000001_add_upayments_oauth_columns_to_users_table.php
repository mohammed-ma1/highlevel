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
            // Separate GoHighLevel OAuth tokens for the UPayments marketplace app.
            $table->text('upayments_lead_access_token')->nullable();
            $table->text('upayments_lead_refresh_token')->nullable();
            $table->string('upayments_lead_token_type', 20)->nullable();
            $table->unsignedInteger('upayments_lead_expires_in')->nullable();
            $table->timestamp('upayments_lead_token_expires_at')->nullable();
            $table->text('upayments_lead_scope')->nullable();

            $table->string('upayments_lead_refresh_token_id')->nullable();
            $table->string('upayments_lead_user_type', 50)->nullable();
            $table->string('upayments_lead_company_id', 50)->nullable();
            $table->string('upayments_lead_location_id', 50)->nullable()->index();
            $table->string('upayments_lead_user_id', 50)->nullable();

            $table->boolean('upayments_lead_is_bulk_installation')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'upayments_lead_access_token',
                'upayments_lead_refresh_token',
                'upayments_lead_token_type',
                'upayments_lead_expires_in',
                'upayments_lead_token_expires_at',
                'upayments_lead_scope',
                'upayments_lead_refresh_token_id',
                'upayments_lead_user_type',
                'upayments_lead_company_id',
                'upayments_lead_location_id',
                'upayments_lead_user_id',
                'upayments_lead_is_bulk_installation',
            ]);
        });
    }
};

