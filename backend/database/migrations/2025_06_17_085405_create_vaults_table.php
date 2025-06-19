<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaults', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->index();
            $table->text('description')->nullable();
            $table->enum('data_type', ['card', 'ssn', 'bank_account', 'custom'])->index();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active')->index();

            // PCI DSS Compliance Fields
            $table->string('encryption_algorithm', 50)->default('AES-256-GCM');
            $table->text('encryption_key_reference');
            $table->integer('max_tokens')->default(1000000);
            $table->integer('current_token_count')->default(0);

            // Access Control
            $table->json('allowed_operations')->nullable();
            $table->json('access_restrictions')->nullable();

            // Retention and Compliance
            $table->integer('retention_days')->default(2555);
            $table->timestamp('last_key_rotation')->nullable();
            $table->integer('key_rotation_interval_days')->default(365);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['data_type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaults');
    }
};

