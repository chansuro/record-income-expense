<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title')->nullable();

            // GPT model used
            $table->string('model')->default('gpt-5.5');

            // Optional system prompt
            $table->longText('system_prompt')->nullable();

            // Conversation status
            $table->enum('status', [
                'active',
                'archived',
                'deleted'
            ])->default('active');

            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
