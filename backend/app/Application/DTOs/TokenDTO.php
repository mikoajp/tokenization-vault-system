<?php

namespace App\Application\DTOs;

use App\Domain\Tokenization\Models\Token;

class TokenDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $vaultId,
        public readonly string $tokenValue,
        public readonly ?string $formatPreservedToken,
        public readonly string $tokenType,
        public readonly string $status,
        public readonly array $metadata,
        public readonly ?string $expiresAt,
        public readonly int $keyVersion,
        public readonly int $usageCount,
        public readonly ?string $lastUsedAt,
        public readonly string $createdAt,
        public readonly string $updatedAt
    ) {}

    public static function fromModel(Token $token): self
    {
        return new self(
            id: $token->id,
            vaultId: $token->vault_id,
            tokenValue: $token->token_value,
            formatPreservedToken: $token->format_preserved_token,
            tokenType: $token->token_type,
            status: $token->status,
            metadata: $token->metadata ?? [],
            expiresAt: $token->expires_at?->toISOString(),
            keyVersion: $token->key_version,
            usageCount: $token->usage_count,
            lastUsedAt: $token->last_used_at?->toISOString(),
            createdAt: $token->created_at->toISOString(),
            updatedAt: $token->updated_at->toISOString()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'vault_id' => $this->vaultId,
            'token_value' => $this->tokenValue,
            'format_preserved_token' => $this->formatPreservedToken,
            'token_type' => $this->tokenType,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'expires_at' => $this->expiresAt,
            'key_version' => $this->keyVersion,
            'usage_count' => $this->usageCount,
            'last_used_at' => $this->lastUsedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}