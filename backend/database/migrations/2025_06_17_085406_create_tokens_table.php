<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vault_id');
            $table->string('token_value', 255)->unique();
            $table->text('original_data_hash');
            $table->text('encrypted_data');
            $table->string('data_hash', 64)->index();
            $table->string('format_preserved_token', 255)->nullable();

            // Token Metadata
            $table->enum('token_type', ['random', 'format_preserving', 'sequential'])->default('random');
            $table->json('metadata')->nullable();
            $table->string('checksum', 64);

            // Usage Tracking
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // PCI DSS Compliance
            $table->string('key_version', 20);
            $table->enum('status', ['active', 'expired', 'revoked', 'compromised'])->default('active');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vault_id')->references('id')->on('vaults')->onDelete('cascade');
            $table->index(['vault_id', 'status']);
            $table->index(['vault_id', 'data_hash']);
            $table->index(['token_value', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};
