<?php

namespace App\Application\Commands;

class UpdateVaultCommand
{
    public function __construct(
        public readonly string $vaultId,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?string $status = null,
        public readonly ?int $maxTokens = null,
        public readonly ?array $allowedOperations = null,
        public readonly ?array $accessRestrictions = null,
        public readonly ?int $retentionDays = null
    ) {}

    public function getUpdateData(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'max_tokens' => $this->maxTokens,
            'allowed_operations' => $this->allowedOperations,
            'access_restrictions' => $this->accessRestrictions,
            'retention_days' => $this->retentionDays,
        ], fn($value) => $value !== null);
    }
}