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
        Schema::create('millages', function (Blueprint $table) {
            $table->id();
            $table->string('business_millage', 60);
            $table->string('personal_millage', 60);
            $table->date('millage_date');
            $table->foreignId('user_id')->constrained()->nullable();
            $table->string('document', 60);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('millages');
    }
};
