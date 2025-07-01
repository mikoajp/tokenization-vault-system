<?php

namespace App\Domain\Tokenization\Events;

use App\Domain\Tokenization\Models\Token;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TokenUsed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Token $token
    ) {}

    public function getTokenId(): string
    {
        return $this->token->getId()->getValue();
    }

    public function getVaultId(): string
    {
        return $this->token->getVaultId()->getValue();
    }

    public function getUsageCount(): int
    {
        return $this->token->usage_count;
    }

    public function getLastUsedAt(): string
    {
        return $this->token->last_used_at->toISOString();
    }
}