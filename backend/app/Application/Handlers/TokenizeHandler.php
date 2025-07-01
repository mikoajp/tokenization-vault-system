<?php

namespace App\Application\Handlers;

use App\Application\Commands\TokenizeCommand;
use App\Application\DTOs\TokenDTO;
use App\Domain\Tokenization\Services\TokenizationDomainService;
use App\Domain\Tokenization\ValueObjects\TokenType;
use App\Domain\Vault\ValueObjects\VaultId;
use Carbon\Carbon;

class TokenizeHandler
{
    public function __construct(
        private TokenizationDomainService $tokenizationDomainService
    ) {}

    public function handle(TokenizeCommand $command): TokenDTO
    {
        $vaultId = new VaultId($command->vaultId);
        $tokenType = new TokenType($command->tokenType);
        $expiresAt = $command->expiresAt ? Carbon::parse($command->expiresAt) : null;

        $token = $this->tokenizationDomainService->createToken(
            vaultId: $vaultId,
            sensitiveData: $command->data,
            tokenType: $tokenType,
            metadata: $command->metadata,
            expiresAt: $expiresAt
        );

        return TokenDTO::fromModel($token);
    }
}