<?php

namespace App\Application\Services;

use App\Application\Commands\TokenizeCommand;
use App\Application\Commands\DetokenizeCommand;
use App\Application\Commands\BulkTokenizeCommand;
use App\Application\Commands\RevokeTokenCommand;
use App\Application\Handlers\TokenizeHandler;
use App\Application\Handlers\DetokenizeHandler;
use App\Application\DTOs\TokenDTO;
use App\Domain\Tokenization\Services\TokenizationDomainService;
use App\Domain\Tokenization\ValueObjects\TokenId;
use App\Domain\Tokenization\ValueObjects\TokenValue;
use App\Domain\Tokenization\ValueObjects\TokenStatus;
use App\Domain\Tokenization\ValueObjects\TokenType;
use App\Domain\Tokenization\Repositories\TokenRepositoryInterface;
use App\Domain\Vault\ValueObjects\VaultId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TokenizationApplicationService
{
    public function __construct(
        private TokenizationDomainService $tokenizationDomainService,
        private TokenRepositoryInterface $tokenRepository,
        private TokenizeHandler $tokenizeHandler,
        private DetokenizeHandler $detokenizeHandler
    ) {}

    public function tokenize(TokenizeCommand $command): TokenDTO
    {
        return $this->tokenizeHandler->handle($command);
    }

    public function detokenize(DetokenizeCommand $command): array
    {
        $sensitiveData = $this->detokenizeHandler->handle($command);

        return [
            'token_value' => $command->tokenValue,
            'data' => $sensitiveData,
        ];
    }

    public function bulkTokenize(BulkTokenizeCommand $command): array
    {
        $vaultId = new VaultId($command->vaultId);
        $tokenType = new TokenType($command->tokenType);

        return $this->tokenizationDomainService->bulkTokenize(
            $vaultId,
            $command->dataItems,
            $tokenType,
            $command->commonMetadata
        );
    }

    public function bulkDetokenize(array $tokenValues): array
    {
        return $this->tokenizationDomainService->bulkDetokenize($tokenValues);
    }

    public function revokeToken(RevokeTokenCommand $command): TokenDTO
    {
        $tokenId = new TokenId($command->tokenId);
        
        $token = $this->tokenizationDomainService->revokeToken($tokenId, $command->reason);

        return TokenDTO::fromModel($token);
    }

    public function getToken(string $tokenId): TokenDTO
    {
        $token = $this->tokenizationDomainService->getToken(new TokenId($tokenId));
        return TokenDTO::fromModel($token);
    }

    public function searchTokens(
        string $vaultId,
        array $searchCriteria,
        int $limit = 100
    ): array {
        return $this->tokenizationDomainService->searchTokens(
            new VaultId($vaultId),
            $searchCriteria,
            $limit
        );
    }

    public function getTokens(
        int $perPage = 15,
        ?string $vaultId = null,
        ?string $status = null,
        ?string $tokenType = null,
        ?string $search = null
    ): LengthAwarePaginator {
        $vaultIdVO = $vaultId ? new VaultId($vaultId) : null;
        $statusVO = $status ? new TokenStatus($status) : null;
        $typeVO = $tokenType ? new TokenType($tokenType) : null;

        return $this->tokenRepository->paginate($perPage, $vaultIdVO, $statusVO, $typeVO, $search);
    }

    public function getTokenStatistics(string $vaultId): array
    {
        return $this->tokenizationDomainService->getTokenStatistics(new VaultId($vaultId));
    }

    public function cleanupExpiredTokens(): array
    {
        $count = $this->tokenizationDomainService->cleanupExpiredTokens();

        return [
            'cleaned_tokens' => $count,
            'timestamp' => now()->toISOString(),
        ];
    }
}