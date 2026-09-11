<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {

            $table->id();

            $table->foreignId('conversation_id')
                ->constrained()
                ->cascadeOnDelete();

            // user | assistant | system | tool
            $table->enum('role', [
                'system',
                'user',
                'assistant',
                'tool'
            ]);

            $table->longText('content');

            // OpenAI model that generated this message
            $table->string('model')->nullable();

            // Usage tracking
            $table->integer('prompt_tokens')->default(0);

            $table->integer('completion_tokens')->default(0);

            $table->integer('total_tokens')->default(0);

            // Response API ID
            $table->string('response_id')->nullable();

            // Store raw response for debugging
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('conversation_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
