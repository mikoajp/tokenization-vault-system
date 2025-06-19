<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('report_type', 50);
            $table->string('report_name', 200);
            $table->date('period_start');
            $table->date('period_end');

            // Report Status
            $table->enum('status', ['generating', 'completed', 'failed', 'archived'])->default('generating');
            $table->text('file_path')->nullable();
            $table->string('file_hash', 64)->nullable();

            // Report Metadata
            $table->json('filters_applied')->nullable();
            $table->json('summary_statistics')->nullable();
            $table->integer('total_records')->nullable();
            $table->integer('generation_time_seconds')->nullable();

            // Access Control
            $table->string('generated_by', 100);
            $table->json('access_granted_to')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['report_type', 'status']);
            $table->index(['period_start', 'period_end']);
            $table->index('generated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_reports');
    }
};
