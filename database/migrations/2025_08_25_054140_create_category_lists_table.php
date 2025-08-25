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
        Schema::create('category_lists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 60);
            $table->enum('type', ['income', 'dailyexp', 'recurringexp', 'paymentmethod', 'paymentincome', 'paymentmethodother']);
            $table->unsignedBigInteger('user_id')->nullable()->index('category_lists_user_id_foreign');
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
            $table->string('icon', 45)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_lists');
    }
};
