<?php

namespace App\Application\DTOs;

use App\Domain\Vault\Models\Vault;

class VaultDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $dataType,
        public readonly string $status,
        public readonly string $encryptionAlgorithm,
        public readonly int $maxTokens,
        public readonly int $currentTokenCount,
        public readonly array $allowedOperations,
        public readonly ?array $accessRestrictions,
        public readonly int $retentionDays,
        public readonly int $keyRotationIntervalDays,
        public readonly ?string $lastKeyRotation,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {}

    public static function fromModel(Vault $vault): self
    {
        return new self(
            id: $vault->id,
            name: $vault->name,
            description: $vault->description,
            dataType: $vault->data_type,
            status: $vault->status,
            encryptionAlgorithm: $vault->encryption_algorithm,
            maxTokens: $vault->max_tokens,
            currentTokenCount: $vault->current_token_count,
            allowedOperations: $vault->allowed_operations ?? [],
            accessRestrictions: $vault->access_restrictions,
            retentionDays: $vault->retention_days,
            keyRotationIntervalDays: $vault->key_rotation_interval_days,
            lastKeyRotation: $vault->last_key_rotation?->toISOString(),
            createdAt: $vault->created_at->toISOString(),
            updatedAt: $vault->updated_at->toISOString()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'data_type' => $this->dataType,
            'status' => $this->status,
            'encryption_algorithm' => $this->encryptionAlgorithm,
            'max_tokens' => $this->maxTokens,
            'current_token_count' => $this->currentTokenCount,
            'allowed_operations' => $this->allowedOperations,
            'access_restrictions' => $this->accessRestrictions,
            'retention_days' => $this->retentionDays,
            'key_rotation_interval_days' => $this->keyRotationIntervalDays,
            'last_key_rotation' => $this->lastKeyRotation,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}