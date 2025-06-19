<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 8);

            // Access Control
            $table->json('vault_permissions');
            $table->json('operation_permissions');
            $table->json('ip_whitelist')->nullable();
            $table->integer('rate_limit_per_hour')->default(1000);

            // Key Management
            $table->enum('status', ['active', 'inactive', 'revoked'])->default('active')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('usage_count')->default(0);

            // Owner Information
            $table->string('owner_type', 50)->nullable();
            $table->string('owner_id', 100)->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at']);
            $table->index('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
