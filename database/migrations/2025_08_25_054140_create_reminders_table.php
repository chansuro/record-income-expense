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
        Schema::create('reminders', function (Blueprint $table) {
            $table->integer('id', true);
            $table->enum('is_alerm', ['Y', 'N'])->nullable()->default('N');
            $table->bigInteger('user_id')->nullable();
            $table->string('reminder_time', 10)->nullable();
            $table->set('repeat_on', ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'])->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
