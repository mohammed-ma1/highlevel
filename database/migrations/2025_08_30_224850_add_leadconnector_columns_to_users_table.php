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
            $table->text('lead_access_token')->nullable();     // encrypt via casts
            $table->text('lead_refresh_token')->nullable();    // encrypt via casts
            $table->string('lead_token_type', 20)->nullable(); // usually "Bearer"
            $table->unsignedInteger('lead_expires_in')->nullable(); // raw seconds, optional
            $table->timestamp('lead_token_expires_at')->nullable(); // computed expiry

            $table->text('lead_scope')->nullable();            // store space-separated or JSON

            // ids from response
            $table->string('lead_refresh_token_id')->nullable();
            $table->string('lead_user_type', 50)->nullable();  // "Location"
            $table->string('lead_company_id', 50)->nullable();
            $table->string('lead_location_id', 50)->nullable()->index();
            $table->string('lead_user_id', 50)->nullable();

            $table->boolean('lead_is_bulk_installation')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'lead_access_token',
                'lead_refresh_token',
                'lead_token_type',
                'lead_expires_in',
                'lead_token_expires_at',
                'lead_scope',
                'lead_refresh_token_id',
                'lead_user_type',
                'lead_company_id',
                'lead_location_id',
                'lead_user_id',
                'lead_is_bulk_installation',
            ]);
        });
    }
};
