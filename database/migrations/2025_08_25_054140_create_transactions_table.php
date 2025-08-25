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
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 60)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index('transactions_user_id_foreign');
            $table->float('amount');
            $table->unsignedBigInteger('category_list_id')->nullable()->index('transactions_category_list_id_foreign');
            $table->enum('type', ['income', 'expenses']);
            $table->text('document')->nullable();
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
            $table->string('paymentmethod');
            $table->dateTime('transaction_date')->nullable();
            $table->enum('is_recurring', ['Y', 'N'])->nullable()->default('N');
            $table->string('recurring_period', 45)->nullable();
            $table->bigInteger('parent_transaction')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
