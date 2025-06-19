<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vault_id');
            $table->string('policy_name', 100);

            // Retention Rules
            $table->integer('retention_days');
            $table->enum('action_after_retention', ['delete', 'archive', 'anonymize'])->default('delete');
            $table->boolean('auto_execute')->default(true);

            // Execution Schedule
            $table->string('cron_schedule', 50)->default('0 2 * * *');
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamp('next_execution_at')->nullable();

            // Policy Status
            $table->enum('status', ['active', 'inactive', 'paused'])->default('active');
            $table->json('execution_log')->nullable();

            $table->timestamps();

            $table->foreign('vault_id')->references('id')->on('vaults')->onDelete('cascade');
            $table->index(['vault_id', 'status']);
            $table->index('next_execution_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_policies');
    }
};
