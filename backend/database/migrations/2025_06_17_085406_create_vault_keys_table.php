<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vault_id');
            $table->string('key_version', 20)->index();
            $table->text('encrypted_key');
            $table->text('key_hash');
            $table->enum('status', ['active', 'retired', 'compromised'])->default('active');
            $table->timestamp('activated_at');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->foreign('vault_id')->references('id')->on('vaults')->onDelete('cascade');
            $table->unique(['vault_id', 'key_version']);
            $table->index(['vault_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_keys');
    }
};
