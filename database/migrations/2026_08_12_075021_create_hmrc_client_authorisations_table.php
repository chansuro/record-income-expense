<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hmrc_client_authorisations', function (Blueprint $table) {
            $table->id();

            /*
             * AppTax customer
             */
            $table->unsignedBigInteger('user_id');

            /*
             * HMRC environment
             */
            $table->string('environment', 20)
                ->default('sandbox');

            /*
             * Service for which AppTax has requested
             * client authorisation.
             *
             * Example:
             * MTD-IT
             */
            $table->string('service', 50)
                ->default('MTD-IT');

            /*
             * Customer type
             *
             * Example:
             * personal
             */
            $table->string('client_type', 30)
                ->nullable();

            /*
             * HMRC client identifier type.
             *
             * For Income Tax:
             * ni
             */
            $table->string('client_id_type', 30)
                ->nullable();

            /*
             * Customer NINO.
             *
             * We will encrypt this in the Laravel model.
             */
            $table->text('client_id')->nullable();

            /*
             * HMRC invitation ID
             */
            $table->string('invitation_id', 100)
                ->nullable();

            /*
             * URL returned by HMRC where the customer
             * accepts/rejects the agent invitation.
             */
            $table->text('client_action_url')->nullable();

            /*
             * main / supporting
             */
            $table->string('agent_type', 30)
                ->default('main');

            /*
             * Invitation / relationship status.
             *
             * Examples:
             * Pending
             * Accepted
             * Rejected
             * Cancelled
             * Expired
             * Deauthorised
             */
            $table->string('status', 30)
                ->default('Pending');

            /*
             * Invitation expiry
             */
            $table->timestamp('expires_at')->nullable();

            /*
             * Client accepted invitation
             */
            $table->timestamp('accepted_at')->nullable();

            /*
             * Last time AppTax checked relationship
             * status with HMRC.
             */
            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('invitation_id');
            $table->index('status');
            $table->index(['environment', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_client_authorisations');
    }
};