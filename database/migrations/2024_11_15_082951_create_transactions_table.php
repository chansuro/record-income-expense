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
            $table->id();   
            $table->string('title', 60);
            $table->foreignId('user_id')->constrained()->nullable();
            $table->float('amount', 8, 2);
            $table->foreignId('category_list_id')->constrained()->nullable();
            $table->enum('type', ['income', 'expenses']);
            $table->string('document', 60);
            $table->enum('status', [0, 1])->default(1);
            $table->timestamps();
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
