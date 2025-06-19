<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vault_id')->nullable();
            $table->uuid('token_id')->nullable();

            // Operation Details
            $table->string('operation', 50)->index();
            // Dodaj walidację w modelu zamiast enum

            $table->enum('result', ['success', 'failure', 'partial'])->index();
            $table->text('error_message')->nullable();

            // Security Context
            $table->string('user_id', 100)->nullable()->index();
            $table->string('api_key_id', 100)->nullable()->index();
            $table->string('session_id', 100)->nullable();
            $table->ipAddress('ip_address')->index();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 100)->index();

            // Request/Response Data (sanitized)
            $table->json('request_metadata')->nullable();
            $table->json('response_metadata')->nullable();
            $table->integer('processing_time_ms')->nullable();

            // Compliance Fields
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low')->index();
            $table->boolean('pci_relevant')->default(true)->index();
            $table->string('compliance_reference', 100)->nullable();

            $table->timestamps();

            // Foreign Keys
            $table->foreign('vault_id')->references('id')->on('vaults')->onDelete('set null');

            // TODO: Add this foreign key after creating tokens table migration
            // $table->foreign('token_id')->references('id')->on('tokens')->onDelete('set null');

            // Indexes for performance
            $table->index(['operation', 'result', 'created_at']);
            $table->index(['vault_id', 'operation', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['pci_relevant', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
