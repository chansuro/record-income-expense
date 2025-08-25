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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->integer('status')->default(1);
            $table->enum('role', ['customer', 'admin'])->nullable()->default('customer');
            $table->rememberToken();
            $table->string('subscription_id', 45)->nullable();
            $table->timestamps();
            $table->string('phone');
            $table->string('avatar', 45)->nullable();
            $table->string('isemailverified', 45)->nullable();
            $table->string('stripe_customer', 45)->nullable();
            $table->text('suspend_reason')->nullable();
            $table->string('fcm_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
