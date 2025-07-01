<?php

namespace App\Domain\Tokenization\Repositories;

use App\Domain\Tokenization\Models\Token;
use App\Domain\Tokenization\ValueObjects\TokenId;
use App\Domain\Tokenization\ValueObjects\TokenValue;
use App\Domain\Tokenization\ValueObjects\TokenStatus;
use App\Domain\Tokenization\ValueObjects\TokenType;
use App\Domain\Vault\ValueObjects\VaultId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TokenRepositoryInterface
{
    public function findById(TokenId $id): ?Token;

    public function findByIdOrFail(TokenId $id): Token;

    public function findByTokenValue(TokenValue $tokenValue): ?Token;

    public function findByTokenValueOrFail(TokenValue $tokenValue): Token;

    public function findByDataHash(string $dataHash, VaultId $vaultId): ?Token;

    public function findByVault(VaultId $vaultId): Collection;

    public function findByStatus(TokenStatus $status): Collection;

    public function findByType(TokenType $type): Collection;

    public function findActiveTokens(): Collection;

    public function findExpiredTokens(): Collection;

    public function findExpiringTokens(int $hours = 24): Collection;

    public function findUnusedTokens(int $days = 90): Collection;

    public function save(Token $token): Token;

    public function delete(TokenId $id): bool;

    public function paginate(
        int $perPage = 15,
        ?VaultId $vaultId = null,
        ?TokenStatus $status = null,
        ?TokenType $type = null,
        ?string $search = null
    ): LengthAwarePaginator;

    public function getStatistics(VaultId $vaultId): array;

    public function getTotalCount(VaultId $vaultId): int;

    public function getActiveCount(VaultId $vaultId): int;

    public function exists(TokenId $id): bool;

    public function existsByTokenValue(TokenValue $tokenValue): bool;

    public function bulkUpdateStatus(array $tokenIds, TokenStatus $status): int;

    public function bulkDelete(array $tokenIds): int;

    public function searchByMetadata(array $criteria, ?VaultId $vaultId = null): Collection;
}