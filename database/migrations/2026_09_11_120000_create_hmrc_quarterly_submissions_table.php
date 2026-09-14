<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hmrc_quarterly_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('authorisation_id');
            $table->string('environment', 20);
            $table->string('business_id', 20);
            $table->string('tax_year', 7);
            $table->string('test_scenario')->nullable();
            $table->date('period_start_date');
            $table->date('period_end_date');
            $table->longText('payload'); // Encrypted by the model; never store OAuth tokens.
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('hmrc_http_status')->nullable();
            $table->string('hmrc_code')->nullable();
            $table->string('correlation_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'environment', 'business_id', 'tax_year'], 'hmrc_quarterly_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_quarterly_submissions');
    }
};
