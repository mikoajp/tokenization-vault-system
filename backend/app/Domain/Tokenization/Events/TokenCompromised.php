<?php

namespace App\Domain\Tokenization\Events;

use App\Domain\Tokenization\Models\Token;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TokenCompromised
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Token $token,
        public readonly ?string $reason = null
    ) {}

    public function getTokenId(): string
    {
        return $this->token->getId()->getValue();
    }

    public function getVaultId(): string
    {
        return $this->token->getVaultId()->getValue();
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getTokenValue(): string
    {
        return $this->token->getTokenValue()->getValue();
    }

    public function isCritical(): bool
    {
        return true; // Token compromise is always critical
    }
}