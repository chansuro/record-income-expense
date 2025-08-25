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
        Schema::create('billings', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('email', 45)->nullable();
            $table->integer('user_id')->nullable();
            $table->string('subscription_id', 45)->nullable();
            $table->string('invoice_id', 45)->nullable();
            $table->string('amount', 45)->nullable();
            $table->string('invoice_status', 10)->nullable();
            $table->string('invoice_date', 45)->nullable();
            $table->string('subscription_from', 45)->nullable();
            $table->string('subscription_to', 45)->nullable();
            $table->text('invoice_link')->nullable();
            $table->string('product_id', 45)->nullable();
            $table->string('currency', 45)->nullable();
            $table->string('plan_id', 45)->nullable();
            $table->string('customer_id', 45)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
