<?php

namespace App\Domain\Tokenization\Events;

use App\Domain\Tokenization\Models\Token;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TokenCreated
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

    public function getTokenType(): string
    {
        return $this->token->getType()->getValue();
    }

    public function isFormatPreserving(): bool
    {
        return $this->token->getType()->isFormatPreserving();
    }
}