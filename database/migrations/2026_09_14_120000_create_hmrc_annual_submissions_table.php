<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hmrc_annual_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('authorisation_id');
            $table->string('environment', 20);
            $table->string('business_id', 20);
            $table->string('tax_year', 7);
            $table->string('test_scenario')->nullable();
            $table->longText('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('hmrc_http_status')->nullable();
            $table->string('hmrc_code')->nullable();
            $table->string('correlation_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'environment', 'business_id', 'tax_year'], 'hmrc_annual_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hmrc_annual_submissions');
    }
};
