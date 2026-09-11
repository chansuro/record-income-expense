<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_agent_connections', function (Blueprint $table) {
            $table->id();

            // HMRC Agent Reference Number
            $table->string('arn')->nullable();

            // sandbox / production
            $table->string('environment', 20)->default('sandbox');

            // Store encrypted using Laravel encrypted casts
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();

            // Access token expiry
            $table->timestamp('expires_at')->nullable();

            // Granted OAuth scopes
            $table->text('scope')->nullable();

            // Admin who connected AppTax to HMRC
            $table->unsignedBigInteger('connected_by')->nullable();

            // Date/time OAuth connection was established
            $table->timestamp('connected_at')->nullable();

            // Last successful token refresh
            $table->timestamp('last_refreshed_at')->nullable();

            // Connection status
            $table->boolean('is_active')->default(false);

            $table->timestamps();

            /*
             * Only one AppTax HMRC connection per environment.
             *
             * sandbox    -> one connection
             * production -> one connection
             */
            $table->unique('environment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_agent_connections');
    }
};
