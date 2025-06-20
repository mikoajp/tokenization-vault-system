<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('type', 100)->index();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->index();
            $table->enum('status', ['active', 'acknowledged', 'resolved', 'false_positive'])
                ->default('active')->index();

            $table->string('user_id', 100)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->uuid('vault_id')->nullable()->index();

            $table->text('message');
            $table->integer('count')->default(1);
            $table->timestamp('first_occurrence');
            $table->timestamp('last_occurrence');

            $table->json('metadata')->nullable();

            $table->string('acknowledged_by', 100)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('resolved_by', 100)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            $table->uuid('triggering_audit_log_id')->nullable()->index();

            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['severity', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index(['ip_address', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
    }
};
